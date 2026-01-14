<?php

namespace App\Services\AI;

use App\DTOs\ComposedPrompt;
use App\DTOs\RetrievalResult;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Services\AskAI\DTO\AskAIResponse;
use App\Services\AskAI\ResponseFormatter;
use App\Services\Prompts\PromptComposer;
use App\Services\Prompts\PromptTemplateService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced Ask service integrating all three phases:
 * - Phase 1: Intent Classification
 * - Phase 2: Prompt Template System
 * - Phase 3: Enhanced Knowledge Retrieval
 */
class EnhancedAskService
{
    private array $config;

    public function __construct(
        private readonly IntentAnalyzerService $intentAnalyzer,
        private readonly ContextRetrievalService $contextRetrieval,
        private readonly PromptTemplateService $templateService,
        private readonly PromptComposer $promptComposer,
        private readonly ResponseFormatter $formatter,
    ) {
        $this->config = config('askai', []);
    }

    /**
     * Process a user question with full pipeline integration.
     *
     * @param array<array{role: string, content: string}> $conversationHistory
     */
    public function ask(
        Project $project,
        string $question,
        array $conversationHistory = [],
        ?string $conversationId = null,
    ): EnhancedAskResponse {
        $startTime = microtime(true);

        try {
            // Phase 1: Analyze intent
            $intentAnalysis = $this->intentAnalyzer->analyze(
                $project,
                $question,
                $conversationHistory,
                $conversationId
            );

            Log::info('EnhancedAsk: Intent analyzed', [
                'project_id' => $project->id,
                'intent_type' => $intentAnalysis->intent_type->value,
                'confidence' => $intentAnalysis->confidence_score,
                'complexity' => $intentAnalysis->complexity_estimate->value,
            ]);

            // Check if clarification is needed
            if ($intentAnalysis->requires_clarification && $intentAnalysis->confidence_score < 0.5) {
                return EnhancedAskResponse::needsClarification(
                    $intentAnalysis->clarification_questions,
                    $intentAnalysis
                );
            }

            // Phase 3: Retrieve relevant context
            $retrievalResult = $this->contextRetrieval->retrieve(
                $project,
                $intentAnalysis,
                $question,
                [
                    'max_chunks' => $this->getMaxChunks($intentAnalysis),
                    'token_budget' => $this->getTokenBudget($intentAnalysis),
                    'include_dependencies' => true,
                ]
            );

            Log::info('EnhancedAsk: Context retrieved', [
                'project_id' => $project->id,
                'chunks' => $retrievalResult->getChunkCount(),
                'files' => $retrievalResult->getFileCount(),
                'estimated_tokens' => $retrievalResult->getTotalTokenEstimate(),
            ]);

            // Handle case with no relevant context
            if ($retrievalResult->isEmpty()) {
                return EnhancedAskResponse::notEnoughContext(
                    $this->suggestMissingContext($intentAnalysis),
                    $intentAnalysis,
                    $retrievalResult
                );
            }

            // Phase 2: Compose prompt using templates
            $composedPrompt = $this->composePrompt(
                $project,
                $intentAnalysis,
                $question,
                $retrievalResult
            );

            // Call AI provider
            $rawResponse = $this->callAI($composedPrompt);

            if ($rawResponse === null) {
                return EnhancedAskResponse::error(
                    'Failed to get a response from the AI provider.',
                    $intentAnalysis,
                    $retrievalResult
                );
            }

            // Format response with citations
            $formattedResponse = $this->formatter->format(
                $rawResponse,
                $retrievalResult->chunks->toArray()
            );

            $duration = (microtime(true) - $startTime) * 1000;

            return EnhancedAskResponse::success(
                $formattedResponse,
                $intentAnalysis,
                $retrievalResult,
                [
                    'processing_time_ms' => round($duration, 2),
                    'model' => $this->config['anthropic']['model'] ?? 'claude-sonnet-4-5-20250929',
                    'prompt_tokens' => $composedPrompt->getEstimatedTokens(),
                ]
            );

        } catch (\Exception $e) {
            Log::error('EnhancedAsk: Failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return EnhancedAskResponse::error(
                'An error occurred while processing your request: ' . $e->getMessage()
            );
        }
    }

    /**
     * Compose the full prompt using Phase 2 templates.
     */
    private function composePrompt(
        Project $project,
        IntentAnalysis $intent,
        string $question,
        RetrievalResult $retrievalResult
    ): ComposedPrompt {
        // Select templates based on intent
        $stack = $project->stack_info ?? [];
        $templates = $this->templateService->selectForIntent(
            $intent->intent_type,
            array_merge(
                [$stack['framework'] ?? 'laravel'],
                $stack['frontend'] ?? []
            )
        );

        // Build context section with retrieved chunks
        $contextSection = $this->promptComposer->buildContextSection(
            $project,
            $retrievalResult->chunks->toArray()
        );

        // Build task section from intent
        $taskSection = $this->promptComposer->buildTaskSection($intent, $question);

        // Build examples section
        $examplesSection = $this->promptComposer->buildExamplesSection($intent->intent_type);

        // Build output format section
        $outputSection = $this->promptComposer->buildOutputSection('markdown');

        // Compose full prompt
        return $this->promptComposer->compose(
            $project,
            $intent,
            $question,
            $retrievalResult->chunks->toArray(),
            [
                'context' => $contextSection,
                'task' => $taskSection,
                'examples' => $examplesSection,
                'output' => $outputSection,
                'entry_points' => $retrievalResult->entryPoints,
                'related_routes' => $retrievalResult->relatedRoutes,
            ]
        );
    }

    /**
     * Call the AI provider.
     */
    private function callAI(ComposedPrompt $prompt): ?string
    {
        $apiKey = $this->config['anthropic']['api_key'] ?? config('services.anthropic.api_key');
        $model = $this->config['anthropic']['model'] ?? 'claude-sonnet-4-5-20250929';
        $maxTokens = $this->config['anthropic']['max_tokens'] ?? 4096;

        if (empty($apiKey)) {
            Log::error('EnhancedAsk: No API key configured');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => $prompt->toMessages(),
            ]);

            if (!$response->successful()) {
                Log::error('EnhancedAsk: API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('content.0.text');

        } catch (\Exception $e) {
            Log::error('EnhancedAsk: API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get max chunks based on intent complexity.
     */
    private function getMaxChunks(IntentAnalysis $intent): int
    {
        $base = $this->config['retrieval']['max_chunks'] ?? 50;

        return match ($intent->complexity_estimate->value) {
            'trivial', 'simple' => (int) ($base * 0.5),
            'medium' => $base,
            'complex' => (int) ($base * 1.5),
            'major' => (int) ($base * 2),
            default => $base,
        };
    }

    /**
     * Get token budget based on intent complexity.
     */
    private function getTokenBudget(IntentAnalysis $intent): int
    {
        $base = config('retrieval.max_token_budget', 100000);

        return match ($intent->complexity_estimate->value) {
            'trivial', 'simple' => (int) ($base * 0.3),
            'medium' => (int) ($base * 0.5),
            'complex' => (int) ($base * 0.8),
            'major' => $base,
            default => (int) ($base * 0.5),
        };
    }

    /**
     * Suggest what context might be missing.
     *
     * @return array<string>
     */
    private function suggestMissingContext(IntentAnalysis $intent): array
    {
        $suggestions = [];
        $domain = $intent->domain_classification['primary'] ?? 'general';

        $domainSuggestions = [
            'auth' => ['app/Http/Controllers/Auth/', 'app/Models/User.php', 'config/auth.php'],
            'users' => ['app/Models/User.php', 'app/Http/Controllers/User/'],
            'api' => ['routes/api.php', 'app/Http/Controllers/Api/'],
            'database' => ['database/migrations/', 'app/Models/'],
            'ui' => ['resources/js/Pages/', 'resources/js/components/'],
        ];

        if (isset($domainSuggestions[$domain])) {
            $suggestions = $domainSuggestions[$domain];
        }

        // Add mentioned files that weren't found
        foreach ($intent->extracted_entities['files'] ?? [] as $file) {
            $suggestions[] = "File matching '{$file}'";
        }

        return array_slice(array_unique($suggestions), 0, 5);
    }
}

/**
 * Enhanced response DTO combining all pipeline outputs.
 */
readonly class EnhancedAskResponse
{
    private function __construct(
        public string $status,
        public ?string $answer,
        public ?IntentAnalysis $intentAnalysis,
        public ?RetrievalResult $retrievalResult,
        public array $citations,
        public array $clarificationQuestions,
        public array $missingContext,
        public array $metadata,
    ) {}

    public static function success(
        AskAIResponse $formatted,
        IntentAnalysis $intent,
        RetrievalResult $retrieval,
        array $metadata = []
    ): self {
        return new self(
            status: 'success',
            answer: $formatted->answer,
            intentAnalysis: $intent,
            retrievalResult: $retrieval,
            citations: $formatted->auditLog,
            clarificationQuestions: [],
            missingContext: [],
            metadata: $metadata,
        );
    }

    public static function needsClarification(
        array $questions,
        IntentAnalysis $intent
    ): self {
        return new self(
            status: 'needs_clarification',
            answer: null,
            intentAnalysis: $intent,
            retrievalResult: null,
            citations: [],
            clarificationQuestions: $questions,
            missingContext: [],
            metadata: [],
        );
    }

    public static function notEnoughContext(
        array $missingContext,
        ?IntentAnalysis $intent = null,
        ?RetrievalResult $retrieval = null
    ): self {
        return new self(
            status: 'not_enough_context',
            answer: null,
            intentAnalysis: $intent,
            retrievalResult: $retrieval,
            citations: [],
            clarificationQuestions: [],
            missingContext: $missingContext,
            metadata: [],
        );
    }

    public static function error(
        string $message,
        ?IntentAnalysis $intent = null,
        ?RetrievalResult $retrieval = null
    ): self {
        return new self(
            status: 'error',
            answer: null,
            intentAnalysis: $intent,
            retrievalResult: $retrieval,
            citations: [],
            clarificationQuestions: [],
            missingContext: [],
            metadata: ['error' => $message],
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }


    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'answer' => $this->answer,
            'intent' => $this->intentAnalysis?->toSummaryArray(),
            'retrieval' => [
                'chunks' => $this->retrievalResult?->getChunkCount() ?? 0,
                'files' => $this->retrievalResult?->getFileCount() ?? 0,
                'entry_points' => $this->retrievalResult?->entryPoints ?? [],
                'estimated_tokens' => $this->retrievalResult?->getTotalTokenEstimate() ?? 0,
            ],
            'citations' => array_map(fn($c) => $c->toArray(), $this->citations),
            'clarification_questions' => $this->clarificationQuestions,
            'missing_context' => $this->missingContext,
            'metadata' => $this->metadata,
        ];
    }
}
