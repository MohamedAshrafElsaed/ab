<?php

namespace App\Services\AskAI;

use App\Models\Project;
use App\Services\AskAI\DTO\AskAIResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AskAIService
{
    private RetrievalService $retrieval;
    private PromptBuilder $promptBuilder;
    private ResponseFormatter $formatter;
    private array $config;

    public function __construct(
        RetrievalService $retrieval,
        PromptBuilder $promptBuilder,
        ResponseFormatter $formatter
    ) {
        $this->retrieval = $retrieval;
        $this->promptBuilder = $promptBuilder;
        $this->formatter = $formatter;
        $this->config = config('askai', []);
    }

    /**
     * Ask a question about a project's codebase.
     */
    public function ask(Project $project, string $question, string $depth = 'quick'): AskAIResponse
    {
        // Check cache first
        $cacheKey = $this->getCacheKey($project, $question, $depth);
        if ($this->config['cache']['enabled'] ?? true) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('AskAI: Cache hit', ['project_id' => $project->id, 'question' => substr($question, 0, 50)]);
                return $cached;
            }
        }

        try {
            // Retrieve relevant chunks
            $retrievalResult = $this->retrieval->retrieve($project, $question, 'deep');
            $chunks = $retrievalResult['chunks'];

            Log::info('AskAI: Retrieved chunks', [
                'project_id' => $project->id,
                'question' => substr($question, 0, 100),
                'chunks_found' => count($chunks),
                'files_searched' => $retrievalResult['files_searched'],
                'total_length' => $retrievalResult['total_length'],
            ]);

            // Handle case with no chunks
            if (empty($chunks)) {
                $response = AskAIResponse::notEnoughContext(
                    $this->suggestMissingFiles($project, $retrievalResult['query_analysis']),
                    'No relevant code chunks found for this query.'
                );
                $this->cacheResponse($cacheKey, $response);
                return $response;
            }

            // Build prompts
            $systemPrompt = $this->promptBuilder->buildSystemPrompt($project);
            $userPrompt = $this->promptBuilder->buildUserPrompt(
                $question,
                $chunks,
                $retrievalResult['query_analysis']
            );

            // Call AI provider
            $rawAnswer = $this->callAIProvider($systemPrompt, $userPrompt);

            if ($rawAnswer === null) {
                return AskAIResponse::error('Failed to get a response from the AI provider. Please try again.');
            }

            // Analyze confidence
            $confidence = $this->analyzeConfidence($rawAnswer, $chunks);

            // Format response
            $response = $this->formatter->format(
                $rawAnswer,
                $chunks,
                $confidence,
                "Retrieved {$retrievalResult['files_searched']} files"
            );

            // Cache successful response
            $this->cacheResponse($cacheKey, $response);

            Log::info('AskAI: Response generated', [
                'project_id' => $project->id,
                'confidence' => $response->confidence,
                'audit_entries' => count($response->auditLog),
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('AskAI: Error processing question', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return AskAIResponse::error("An error occurred while processing your question: {$e->getMessage()}");
        }
    }

    /**
     * Call the configured AI provider.
     */
    private function callAIProvider(string $systemPrompt, string $userPrompt): ?string
    {
        $provider = $this->config['provider'] ?? 'openai';

        return match ($provider) {
            'anthropic' => $this->callAnthropic($systemPrompt, $userPrompt),
            default => $this->callOpenAI($systemPrompt, $userPrompt),
        };
    }

    private function callOpenAI(string $systemPrompt, string $userPrompt): ?string
    {
        $config = $this->config['openai'] ?? [];
        $apiKey = $config['api_key'] ?? null;

        if (!$apiKey) {
            Log::error('AskAI: OpenAI API key not configured');
            return null;
        }

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $config['model'] ?? 'gpt-4-turbo-preview',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'max_tokens' => $config['max_tokens'] ?? 4096,
                    'temperature' => $config['temperature'] ?? 0.1,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::error('AskAI: OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('AskAI: OpenAI request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function callAnthropic(string $systemPrompt, string $userPrompt): ?string
    {
        $config = $this->config['anthropic'] ?? [];
        $apiKey = $config['api_key'] ?? null;

        if (!$apiKey) {
            Log::error('AskAI: Anthropic API key not configured');
            return null;
        }

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $config['model'] ?? 'claude-opus-4-5-20251101',
                    'max_tokens' => $config['max_tokens'] ?? 4096,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if ($response->successful()) {
                $content = $response->json('content');
                return $content[0]['text'] ?? null;
            }

            Log::error('AskAI: Anthropic API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('AskAI: Anthropic request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Analyze the confidence of the AI response.
     */
    private function analyzeConfidence(string $answer, array $chunks): string
    {
        // Quick heuristic analysis without additional API call
        $hasNotEnoughContext = preg_match('/not enough context|cannot determine|unable to/i', $answer);
        $hasCitations = preg_match('/`[a-zA-Z0-9_\/\.\-]+`.*:?L?\d+/i', $answer);
        $hasCodeBlocks = preg_match('/```[\s\S]+?```/', $answer);
        $mentionsFiles = preg_match_all('/`[a-zA-Z0-9_\/\.\-]+\.[a-z]+`/i', $answer);

        if ($hasNotEnoughContext) {
            return 'low';
        }

        $score = 0;
        if ($hasCitations) $score += 2;
        if ($hasCodeBlocks) $score += 1;
        if ($mentionsFiles >= 2) $score += 1;
        if (count($chunks) >= 3) $score += 1;

        if ($score >= 4) return 'high';
        if ($score >= 2) return 'medium';
        return 'low';
    }

    /**
     * Suggest files that might be missing based on the query.
     */
    private function suggestMissingFiles(Project $project, array $queryAnalysis): array
    {
        $suggestions = [];

        // Based on query type, suggest relevant directories
        if ($queryAnalysis['is_route_query'] ?? false) {
            $suggestions[] = 'routes/web.php';
            $suggestions[] = 'routes/api.php';
            $suggestions[] = 'Relevant controller files';
        }

        if ($queryAnalysis['is_auth_query'] ?? false) {
            $suggestions[] = 'app/Http/Middleware/Authenticate.php';
            $suggestions[] = 'config/auth.php';
            $suggestions[] = 'Auth-related controllers and guards';
        }

        if ($queryAnalysis['is_db_query'] ?? false) {
            $suggestions[] = 'database/migrations/';
            $suggestions[] = 'app/Models/';
            $suggestions[] = 'Relevant model files';
        }

        // Add path patterns from query
        foreach ($queryAnalysis['path_patterns'] ?? [] as $pattern) {
            $suggestions[] = "Files matching: {$pattern}";
        }

        // Add symbol suggestions
        foreach ($queryAnalysis['symbol_patterns'] ?? [] as $symbol) {
            $suggestions[] = "Files containing: {$symbol}";
        }

        return array_values(array_unique(array_slice($suggestions, 0, 10)));
    }

    private function getCacheKey(Project $project, string $question, string $depth): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'askai_';
        $questionHash = md5(strtolower(trim($question)));
        return "{$prefix}{$project->id}_{$questionHash}_{$depth}";
    }

    private function cacheResponse(string $key, AskAIResponse $response): void
    {
        if (!($this->config['cache']['enabled'] ?? true)) {
            return;
        }

        $ttl = $this->config['cache']['ttl'] ?? 300;
        Cache::put($key, $response, $ttl);
    }
}
