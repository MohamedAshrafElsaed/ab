<?php

namespace App\Services\AskAI;

use App\Services\AskAI\DTO\AskAIResponse;
use App\Services\AskAI\DTO\AuditLogEntry;
use App\Services\AskAI\DTO\RetrievedChunk;

class ResponseFormatter
{
    private PromptBuilder $promptBuilder;
    private array $config;

    public function __construct(PromptBuilder $promptBuilder)
    {
        $this->promptBuilder = $promptBuilder;
        $this->config = config('askai.response', []);
    }

    /**
     * Format the AI response with audit log.
     *
     * @param RetrievedChunk[] $chunks
     */
    public function format(
        string  $rawAnswer,
        array   $chunks,
        string  $confidence = 'medium',
        ?string $processingNote = null
    ): AskAIResponse
    {
        // Detect if this is a "not enough context" response
        $isInsufficientContext = $this->detectInsufficientContext($rawAnswer);

        if ($isInsufficientContext && empty($chunks)) {
            return AskAIResponse::notEnoughContext(
                $this->extractMissingFiles($rawAnswer),
                $rawAnswer
            );
        }

        // Extract quoted snippets to build audit log
        $quotedSnippets = $this->promptBuilder->extractQuotedSnippets($rawAnswer, $chunks);

        // Build audit log
        $auditLog = $this->buildAuditLog($chunks, $quotedSnippets, $rawAnswer);

        // Extract limitations from the response
        $missingDetails = $this->extractLimitations($rawAnswer);

        // Determine final confidence
        $finalConfidence = $this->determineConfidence($rawAnswer, $auditLog, $confidence);

        // Calculate totals
        $totalLength = array_sum(array_map(fn($c) => $c->getContentLength(), $chunks));

        return new AskAIResponse(
            answerMarkdown: $this->cleanupAnswer($rawAnswer),
            auditLog: $auditLog,
            missingDetails: $missingDetails,
            confidence: $finalConfidence,
            chunksUsed: count($chunks),
            totalContentLength: $totalLength,
            processingNote: $processingNote,
        );
    }

    /**
     * Detect if the answer indicates insufficient context.
     */
    private function detectInsufficientContext(string $answer): bool
    {
        $patterns = [
            '/not enough context/i',
            '/insufficient (information|context|data)/i',
            '/cannot (determine|find|locate)/i',
            '/no (relevant )?(code|chunks|files) (found|available)/i',
            '/unable to (answer|determine|find)/i',
            '/don\'t have (enough|sufficient)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $answer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract file suggestions from a "not enough context" response.
     */
    private function extractMissingFiles(string $answer): array
    {
        $files = [];

        // Look for file paths mentioned as needed
        preg_match_all('/`([a-zA-Z0-9_\/\.\-]+\.[a-z]+)`/i', $answer, $matches);
        if (!empty($matches[1])) {
            $files = array_merge($files, $matches[1]);
        }

        // Look for directory suggestions
        preg_match_all('/(?:in|from|check|need)\s+(?:the\s+)?`?([a-zA-Z0-9_\/]+\/)`?/i', $answer, $dirMatches);
        if (!empty($dirMatches[1])) {
            $files = array_merge($files, $dirMatches[1]);
        }

        // Look for file type suggestions
        preg_match_all('/(?:controller|model|view|migration|config|route)s?\s+(?:file|files)?/i', $answer, $typeMatches);
        foreach ($typeMatches[0] ?? [] as $type) {
            $type = strtolower(trim($type));
            $files[] = "Relevant {$type}";
        }

        return array_values(array_unique(array_filter($files)));
    }

    /**
     * Build the audit log from chunks and quoted snippets.
     *
     * @param RetrievedChunk[] $chunks
     * @param array<string, string[]> $quotedSnippets
     * @return AuditLogEntry[]
     */
    private function buildAuditLog(array $chunks, array $quotedSnippets, string $answer): array
    {
        $auditLog = [];
        $maxSnippets = $this->config['max_snippets_per_citation'] ?? 3;

        // Group chunks by file for cleaner audit log
        $chunksByFile = [];
        foreach ($chunks as $chunk) {
            $chunksByFile[$chunk->path][] = $chunk;
        }

        foreach ($chunks as $chunk) {
            // Check if this chunk was actually referenced in the answer
            $wasReferenced = $this->wasChunkReferenced($chunk, $answer);

            if (!$wasReferenced && !isset($quotedSnippets[$chunk->chunkId])) {
                // Still include it but with lower priority (it provided context)
                $snippets = [];
            } else {
                $snippets = array_slice($quotedSnippets[$chunk->chunkId] ?? [], 0, $maxSnippets);
            }

            $auditLog[] = AuditLogEntry::fromRetrievedChunk($chunk, $snippets);
        }

        // Sort by relevance score descending
        usort($auditLog, fn($a, $b) => $b->relevanceScore <=> $a->relevanceScore);

        return $auditLog;
    }

    /**
     * Check if a chunk was referenced in the answer.
     */
    private function wasChunkReferenced(RetrievedChunk $chunk, string $answer): bool
    {
        // Check for path reference
        $pathParts = explode('/', $chunk->path);
        $filename = end($pathParts);

        if (str_contains($answer, $chunk->path) || str_contains($answer, $filename)) {
            return true;
        }

        // Check for line number reference
        $linePattern = "L{$chunk->startLine}";
        if (str_contains($answer, $linePattern)) {
            return true;
        }

        // Check for symbol references
        $symbols = $this->flattenToStrings($chunk->symbolsDeclared);
        foreach ($symbols as $symbol) {
            if (preg_match('/\b' . preg_quote($symbol, '/') . '\b/', $answer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flatten mixed array to string array.
     */
    private function flattenToStrings(mixed $data): array
    {
        if (!is_array($data)) {
            return is_string($data) ? [$data] : [];
        }

        $result = [];
        array_walk_recursive($data, function ($item) use (&$result) {
            if (is_string($item) && $item !== '') {
                $result[] = $item;
            }
        });

        return $result;
    }

    /**
     * Extract limitations mentioned in the answer.
     */
    private function extractLimitations(string $answer): array
    {
        $limitations = [];

        // Look for Limitations section
        if (preg_match('/#+\s*Limitations?\s*\n([\s\S]+?)(?=\n#|$)/i', $answer, $match)) {
            $limitSection = $match[1];
            // Extract bullet points
            preg_match_all('/[-*]\s*(.+)/m', $limitSection, $bullets);
            if (!empty($bullets[1])) {
                $limitations = array_map('trim', $bullets[1]);
            }
        }

        // Look for "cannot determine" patterns
        preg_match_all('/cannot (determine|find|verify|confirm)\s+(.+?)(?:\.|$)/i', $answer, $cannotMatches);
        foreach ($cannotMatches[2] ?? [] as $item) {
            $limitations[] = trim($item);
        }

        return array_values(array_unique(array_filter($limitations)));
    }

    /**
     * Determine final confidence based on multiple factors.
     */
    private function determineConfidence(string $answer, array $auditLog, string $aiSuggestedConfidence): string
    {
        $thresholds = $this->config['confidence'] ?? ['high' => 0.8, 'medium' => 0.5];

        // If AI explicitly said low confidence or not enough context
        if ($this->detectInsufficientContext($answer)) {
            return 'low';
        }

        // Calculate based on audit log coverage
        $referencedCount = 0;
        foreach ($auditLog as $entry) {
            if (!empty($entry->quotedSnippets) || $entry->relevanceScore > 5.0) {
                $referencedCount++;
            }
        }

        $coverageRatio = count($auditLog) > 0 ? $referencedCount / count($auditLog) : 0;

        // Combine AI suggestion with our analysis
        $scores = ['high' => 1.0, 'medium' => 0.6, 'low' => 0.3];
        $aiScore = $scores[strtolower($aiSuggestedConfidence)] ?? 0.5;
        $combinedScore = ($aiScore + $coverageRatio) / 2;

        if ($combinedScore >= $thresholds['high']) {
            return 'high';
        } elseif ($combinedScore >= $thresholds['medium']) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Clean up the answer for presentation.
     */
    private function cleanupAnswer(string $answer): string
    {
        // Remove any system prompt leakage
        $answer = preg_replace('/^(System|Assistant|AI):?\s*/mi', '', $answer);

        // Ensure consistent header formatting
        $answer = preg_replace('/^(#{1,3})\s*(?=\w)/m', '$1 ', $answer);

        // Remove excessive blank lines
        $answer = preg_replace('/\n{4,}/', "\n\n\n", $answer);

        return trim($answer);
    }
}
