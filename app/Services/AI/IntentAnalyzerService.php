<?php

namespace App\Services\AI;

use App\DTOs\IntentAnalysisResult;
use App\Enums\ComplexityLevel;
use App\Enums\IntentType;
use App\Models\IntentAnalysis;
use App\Models\Project;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IntentAnalyzerService
{
    private array $config;
    private string $promptTemplate;

    public function __construct()
    {
        $this->config = config('intent_analyzer', []);
        $this->promptTemplate = $this->loadPromptTemplate();
    }

    /**
     * Analyze user message and return structured intent data.
     *
     * @param array<array{role: string, content: string}> $conversationHistory
     */
    public function analyze(
        Project $project,
        string $userMessage,
        array $conversationHistory = [],
        ?string $conversationId = null,
        ?string $messageId = null,
    ): IntentAnalysis {
        $startTime = microtime(true);

        $conversationId = $conversationId ?? (string) Str::uuid();
        $messageId = $messageId ?? (string) Str::uuid();

        try {
            $systemPrompt = $this->buildSystemPrompt($project, $conversationHistory);
            $result = $this->callClaudeAPI($systemPrompt, $userMessage);

            $processingTime = (microtime(true) - $startTime) * 1000;

            $result = IntentAnalysisResult::fromClaudeResponse(
                $result['parsed'],
                [
                    'processing_time_ms' => round($processingTime, 2),
                    'tokens_used' => $result['tokens'] ?? null,
                    'model' => $result['model'] ?? null,
                    'raw_response_length' => strlen($result['raw'] ?? ''),
                ]
            );

            return $this->persistAnalysis(
                project: $project,
                conversationId: $conversationId,
                messageId: $messageId,
                rawInput: $userMessage,
                result: $result
            );

        } catch (\Exception $e) {
            Log::error('IntentAnalyzer: Analysis failed', [
                'project_id' => $project->id,
                'message' => substr($userMessage, 0, 100),
                'error' => $e->getMessage(),
            ]);

            $failedResult = IntentAnalysisResult::failed($e->getMessage());

            return $this->persistAnalysis(
                project: $project,
                conversationId: $conversationId,
                messageId: $messageId,
                rawInput: $userMessage,
                result: $failedResult
            );
        }
    }

    /**
     * Check if an analysis requires clarification.
     */
    public function needsClarification(IntentAnalysis $analysis): bool
    {
        if ($analysis->requires_clarification) {
            return true;
        }

        if ($analysis->confidence_score < ($this->config['clarification_threshold'] ?? 0.5)) {
            return true;
        }

        if ($analysis->intent_type === IntentType::Unknown) {
            return true;
        }

        return false;
    }

    /**
     * Generate clarification questions for an analysis.
     *
     * @return array<string>
     */
    public function generateClarificationQuestions(IntentAnalysis $analysis): array
    {
        if (!empty($analysis->clarification_questions)) {
            return $analysis->clarification_questions;
        }

        $questions = [];

        if ($analysis->intent_type === IntentType::Unknown) {
            $questions[] = 'Could you describe what you would like me to do in more detail?';
            $questions[] = 'Are you looking to add a feature, fix a bug, write tests, or something else?';
        }

        if (empty($analysis->mentioned_files) && $analysis->intent_type->requiresCodeChanges()) {
            $questions[] = 'Which files or components should this change affect?';
        }

        if ($analysis->complexity_estimate->weight() >= ComplexityLevel::Complex->weight()) {
            $questions[] = 'This seems like a significant change. Would you like to break it down into smaller steps?';
        }

        if ($analysis->confidence_score < 0.5) {
            $questions[] = 'I\'m not entirely sure I understand. Could you rephrase or provide more context?';
        }

        return array_slice($questions, 0, 3);
    }

    /**
     * Re-analyze with additional context from clarification.
     */
    public function reanalyzeWithClarification(
        IntentAnalysis $originalAnalysis,
        string $clarificationMessage
    ): IntentAnalysis {
        $combinedMessage = sprintf(
            "Original request: %s\n\nClarification: %s",
            $originalAnalysis->raw_input,
            $clarificationMessage
        );

        return $this->analyze(
            project: $originalAnalysis->project,
            userMessage: $combinedMessage,
            conversationHistory: [],
            conversationId: $originalAnalysis->conversation_id,
            messageId: (string) Str::uuid(),
        );
    }

    /**
     * Detect if message contains multiple intents.
     *
     * @return array{is_multi_intent: bool, detected_intents: array<string>, suggestion: string|null}
     */
    public function detectMultipleIntents(string $userMessage): array
    {
        $intentIndicators = [
            'feature_request' => ['/\b(add|create|build|implement|new)\b/i', '/\b(feature|functionality)\b/i'],
            'bug_fix' => ['/\b(fix|bug|broken|error|crash|issue)\b/i', '/\b(doesn\'t work|not working)\b/i'],
            'test_writing' => ['/\b(test|tests|testing|spec)\b/i', '/\b(unit test|integration test)\b/i'],
            'refactoring' => ['/\b(refactor|cleanup|clean up|improve|optimize)\b/i'],
            'question' => ['/\b(how|what|where|why|explain)\b/i', '/\?$/'],
        ];

        $detectedIntents = [];

        foreach ($intentIndicators as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $userMessage)) {
                    $detectedIntents[$intent] = true;
                    break;
                }
            }
        }

        $uniqueIntents = array_keys($detectedIntents);
        $isMultiIntent = count($uniqueIntents) > 1;

        $suggestion = null;
        if ($isMultiIntent) {
            $suggestion = 'Your message seems to contain multiple requests. Consider breaking them into separate messages for better results.';
        }

        return [
            'is_multi_intent' => $isMultiIntent,
            'detected_intents' => $uniqueIntents,
            'suggestion' => $suggestion,
        ];
    }

    private function buildSystemPrompt(Project $project, array $conversationHistory): string
    {
        $projectInfo = $this->formatProjectInfo($project);
        $techStack = $this->formatTechStack($project);
        $historyFormatted = $this->formatConversationHistory($conversationHistory);

        return str_replace(
            ['{{PROJECT_INFO}}', '{{TECH_STACK}}', '{{CONVERSATION_HISTORY}}'],
            [$projectInfo, $techStack, $historyFormatted],
            $this->promptTemplate
        );
    }

    private function formatProjectInfo(Project $project): string
    {
        $info = [
            'name' => $project->repo_full_name,
            'default_branch' => $project->default_branch,
            'total_files' => $project->total_files,
            'total_lines' => $project->total_lines,
        ];

        return json_encode($info, JSON_PRETTY_PRINT);
    }

    private function formatTechStack(Project $project): string
    {
        $stack = $project->stack_info ?? [];

        if (empty($stack)) {
            return 'Unknown stack';
        }

        $formatted = [];

        if (!empty($stack['framework'])) {
            $formatted[] = "Framework: {$stack['framework']}";
        }

        if (!empty($stack['frontend'])) {
            $formatted[] = 'Frontend: ' . implode(', ', (array) $stack['frontend']);
        }

        if (!empty($stack['features'])) {
            $formatted[] = 'Features: ' . implode(', ', (array) $stack['features']);
        }

        if (!empty($stack['testing'])) {
            $formatted[] = 'Testing: ' . implode(', ', (array) $stack['testing']);
        }

        return implode("\n", $formatted);
    }

    private function formatConversationHistory(array $history): string
    {
        if (empty($history)) {
            return 'No previous conversation context.';
        }

        $formatted = [];
        $recentHistory = array_slice($history, -5);

        foreach ($recentHistory as $message) {
            $role = ucfirst($message['role'] ?? 'unknown');
            $content = substr($message['content'] ?? '', 0, 500);
            $formatted[] = "$role: $content";
        }

        return implode("\n\n", $formatted);
    }

    /**
     * @return array{parsed: array<string, mixed>, raw: string, tokens: int|null, model: string|null}
     * @throws ConnectionException
     */
    private function callClaudeAPI(string $systemPrompt, string $userMessage): array
    {
        $apiKey = $this->config['anthropic']['api_key'] ?? config('askai.anthropic.api_key');
        $model = $this->config['anthropic']['model'] ?? 'claude-sonnet-4-5-20250929';
        $maxTokens = $this->config['anthropic']['max_tokens'] ?? 1024;

        if (!$apiKey) {
            throw new \RuntimeException('Anthropic API key not configured for intent analyzer');
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => "Analyze this user message:\n\n$userMessage"],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('IntentAnalyzer: API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to call Claude API: ' . $response->status());
        }

        $rawContent = $response->json('content.0.text') ?? '';
        $usage = $response->json('usage') ?? [];

        $parsed = $this->parseJsonResponse($rawContent);

        return [
            'parsed' => $parsed,
            'raw' => $rawContent,
            'tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
            'model' => $model,
        ];
    }


    /**
     * @return array<string, mixed>
     */
    private function parseJsonResponse(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $content = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('IntentAnalyzer: Failed to parse JSON response', [
                'error' => json_last_error_msg(),
                'content' => substr($content, 0, 500),
            ]);

            return $this->getDefaultAnalysis();
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultAnalysis(): array
    {
        return [
            'intent_type' => 'unknown',
            'confidence_score' => 0.3,
            'extracted_entities' => ['files' => [], 'components' => [], 'features' => [], 'symbols' => []],
            'domain_classification' => ['primary' => 'general', 'secondary' => []],
            'complexity_estimate' => 'medium',
            'requires_clarification' => true,
            'clarification_questions' => ['Could you provide more details about what you would like to do?'],
        ];
    }

    private function persistAnalysis(
        Project $project,
        string $conversationId,
        string $messageId,
        string $rawInput,
        IntentAnalysisResult $result
    ): IntentAnalysis {
        return IntentAnalysis::create([
            'project_id' => $project->id,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'raw_input' => $rawInput,
            'intent_type' => $result->intentType->value,
            'confidence_score' => $result->confidenceScore,
            'extracted_entities' => $result->extractedEntities,
            'domain_classification' => $result->domainClassification,
            'complexity_estimate' => $result->complexityEstimate->value,
            'requires_clarification' => $result->requiresClarification,
            'clarification_questions' => $result->clarificationQuestions,
            'metadata' => $result->metadata,
        ]);
    }

    private function loadPromptTemplate(): string
    {
        $templatePath = resource_path('prompts/intent_analyzer.md');

        if (file_exists($templatePath)) {
            return file_get_contents($templatePath);
        }

        return $this->getDefaultPromptTemplate();
    }

    private function extractUserMessageSection(string $prompt): string
    {
        if (preg_match('/<user_message>\s*(.*?)\s*<\/user_message>/s', $prompt, $matches)) {
            return trim($matches[1]);
        }

        return $prompt;
    }

    private function getDefaultPromptTemplate(): string
    {
        return <<<'PROMPT'
            You are an intent classification system. Analyze the user message and respond with a JSON object containing:
            - intent_type: feature_request|bug_fix|test_writing|ui_component|refactoring|question|clarification|unknown
            - confidence_score: 0.0-1.0
            - extracted_entities: {files: [], components: [], features: [], symbols: []}
            - domain_classification: {primary: string, secondary: []}
            - complexity_estimate: trivial|simple|medium|complex|major
            - requires_clarification: boolean
            - clarification_questions: []

            <user_message>
            {{USER_MESSAGE}}
            </user_message>
        PROMPT;
    }
}
