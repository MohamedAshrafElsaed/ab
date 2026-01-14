<?php

namespace App\DTOs;

use App\Services\AskAI\DTO\RetrievedChunk;
use Illuminate\Support\Collection;

/**
 * Result of context retrieval for agent consumption.
 */
readonly class RetrievalResult
{
    /**
     * @param Collection<int, RetrievedChunk> $chunks Ranked relevant chunks
     * @param Collection<int, array{path: string, language: string, relevance: float}> $files Unique files involved
     * @param array<string> $entryPoints Main files to focus on
     * @param array<string, array{path: string, relationship: string, depth: int}> $dependencies Supporting files
     * @param array<array{uri: string, method: string, controller: ?string, name: ?string}> $relatedRoutes Routes that might be affected
     * @param array<string, mixed> $metadata Stats, scores, timing
     */
    public function __construct(
        public Collection $chunks,
        public Collection $files,
        public array $entryPoints,
        public array $dependencies,
        public array $relatedRoutes,
        public array $metadata,
    ) {}

    /**
     * Create an empty result.
     */
    public static function empty(string $reason = 'No relevant context found'): self
    {
        return new self(
            chunks: collect(),
            files: collect(),
            entryPoints: [],
            dependencies: [],
            relatedRoutes: [],
            metadata: [
                'empty' => true,
                'reason' => $reason,
                'retrieved_at' => now()->toIso8601String(),
            ],
        );
    }

    /**
     * Format chunks for injection into prompt.
     */
    public function toPromptContext(): string
    {
        if ($this->chunks->isEmpty()) {
            return "No relevant code context available.\n";
        }

        $context = "<retrieved_context>\n";
        $context .= "<summary>\n";
        $context .= "Files: " . $this->files->count() . " | ";
        $context .= "Chunks: " . $this->chunks->count() . " | ";
        $context .= "Entry Points: " . count($this->entryPoints) . " | ";
        $context .= "Dependencies: " . count($this->dependencies) . "\n";

        if (!empty($this->relatedRoutes)) {
            $routes = array_slice($this->relatedRoutes, 0, 5);
            $routeStr = implode(', ', array_map(fn($r) => $r['method'] . ' ' . $r['uri'], $routes));
            $context .= "Related Routes: " . $routeStr . "\n";
        }

        $context .= "</summary>\n\n";

        // Group chunks by file for better organization
        $byFile = $this->chunks->groupBy(fn(RetrievedChunk $c) => $c->path);

        foreach ($byFile as $path => $fileChunks) {
            $isEntryPoint = in_array($path, $this->entryPoints);
            $context .= "<file path=\"{$path}\"" . ($isEntryPoint ? ' role="entry_point"' : '') . ">\n";

            foreach ($fileChunks as $index => $chunk) {
                /** @var RetrievedChunk $chunk */
                $context .= "<chunk lines=\"{$chunk->startLine}-{$chunk->endLine}\" ";
                $context .= "relevance=\"" . round($chunk->relevanceScore, 2) . "\">\n";

                if (!empty($chunk->symbolsDeclared)) {
                    $symbols = $this->flattenSymbols($chunk->symbolsDeclared);
                    if (!empty($symbols)) {
                        $context .= "<!-- Declares: " . implode(', ', array_slice($symbols, 0, 10)) . " -->\n";
                    }
                }

                $context .= "```" . ($chunk->language ?? '') . "\n";
                $context .= $chunk->content;
                $context .= "\n```\n";
                $context .= "</chunk>\n";
            }

            $context .= "</file>\n\n";
        }

        // Add dependency context
        if (!empty($this->dependencies)) {
            $context .= "<dependencies>\n";
            $grouped = $this->groupDependenciesByDepth();
            foreach ($grouped as $depth => $deps) {
                $context .= "Depth {$depth}: " . implode(', ', array_column($deps, 'path')) . "\n";
            }
            $context .= "</dependencies>\n";
        }

        $context .= "</retrieved_context>";

        return $context;
    }

    /**
     * Get list of unique file paths.
     *
     * @return array<string>
     */
    public function getFileList(): array
    {
        return $this->files->pluck('path')->unique()->values()->toArray();
    }

    /**
     * Estimate total tokens for all chunks.
     */
    public function getTotalTokenEstimate(): int
    {
        $tokensPerChar = config('retrieval.tokens_per_char', 0.25);
        $totalChars = $this->chunks->sum(fn(RetrievedChunk $c) => strlen($c->content));

        return (int) ceil($totalChars * $tokensPerChar);
    }

    /**
     * Limit result to fit within token budget.
     */
    public function limitToTokenBudget(int $budget): self
    {
        $tokensPerChar = config('retrieval.tokens_per_char', 0.25);
        $maxChars = (int) ($budget / $tokensPerChar);
        $currentChars = 0;
        $selectedChunks = collect();
        $selectedFiles = collect();

        // Sort by relevance (already sorted, but ensure)
        $sorted = $this->chunks->sortByDesc(fn(RetrievedChunk $c) => $c->relevanceScore);

        foreach ($sorted as $chunk) {
            $chunkSize = strlen($chunk->content);
            if ($currentChars + $chunkSize > $maxChars) {
                break;
            }

            $selectedChunks->push($chunk);
            $currentChars += $chunkSize;

            if (!$selectedFiles->contains('path', $chunk->path)) {
                $selectedFiles->push([
                    'path' => $chunk->path,
                    'language' => $chunk->language,
                    'relevance' => $chunk->relevanceScore,
                ]);
            }
        }

        // Filter entry points to only include selected files
        $selectedPaths = $selectedChunks->pluck('path')->unique()->toArray();
        $filteredEntryPoints = array_intersect($this->entryPoints, $selectedPaths);

        // Filter dependencies to only include related files
        $filteredDependencies = array_filter(
            $this->dependencies,
            fn($dep) => in_array($dep['path'], $selectedPaths) || $this->hasRelatedFile($dep['path'], $selectedPaths)
        );

        return new self(
            chunks: $selectedChunks,
            files: $selectedFiles,
            entryPoints: array_values($filteredEntryPoints),
            dependencies: $filteredDependencies,
            relatedRoutes: $this->relatedRoutes,
            metadata: array_merge($this->metadata, [
                'token_limited' => true,
                'original_chunks' => $this->chunks->count(),
                'selected_chunks' => $selectedChunks->count(),
                'token_budget' => $budget,
                'estimated_tokens' => $this->estimateTokens($selectedChunks),
            ]),
        );
    }

    /**
     * Check if result is empty.
     */
    public function isEmpty(): bool
    {
        return $this->chunks->isEmpty();
    }

    /**
     * Get chunk count.
     */
    public function getChunkCount(): int
    {
        return $this->chunks->count();
    }

    /**
     * Get file count.
     */
    public function getFileCount(): int
    {
        return $this->files->count();
    }

    /**
     * Get top N most relevant chunks.
     *
     * @return Collection<int, RetrievedChunk>
     */
    public function getTopChunks(int $n): Collection
    {
        return $this->chunks
            ->sortByDesc(fn(RetrievedChunk $c) => $c->relevanceScore)
            ->take($n)
            ->values();
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'chunks' => $this->chunks->map(fn(RetrievedChunk $c) => $c->toArray())->toArray(),
            'files' => $this->files->toArray(),
            'entry_points' => $this->entryPoints,
            'dependencies' => $this->dependencies,
            'related_routes' => $this->relatedRoutes,
            'metadata' => $this->metadata,
            'stats' => [
                'chunk_count' => $this->chunks->count(),
                'file_count' => $this->files->count(),
                'entry_point_count' => count($this->entryPoints),
                'dependency_count' => count($this->dependencies),
                'route_count' => count($this->relatedRoutes),
                'estimated_tokens' => $this->getTotalTokenEstimate(),
            ],
        ];
    }

    /**
     * @return array<int, array<array{path: string, relationship: string, depth: int}>>
     */
    private function groupDependenciesByDepth(): array
    {
        $grouped = [];
        foreach ($this->dependencies as $dep) {
            $depth = $dep['depth'] ?? 1;
            $grouped[$depth][] = $dep;
        }
        ksort($grouped);
        return $grouped;
    }

    /**
     * @param array<string> $paths
     */
    private function hasRelatedFile(string $depPath, array $paths): bool
    {
        $depBase = pathinfo($depPath, PATHINFO_FILENAME);
        foreach ($paths as $path) {
            if (str_contains($path, $depBase)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Collection<int, RetrievedChunk> $chunks
     */
    private function estimateTokens(Collection $chunks): int
    {
        $tokensPerChar = config('retrieval.tokens_per_char', 0.25);
        $totalChars = $chunks->sum(fn(RetrievedChunk $c) => strlen($c->content));
        return (int) ceil($totalChars * $tokensPerChar);
    }

    /**
     * @return array<string>
     */
    private function flattenSymbols(mixed $symbols): array
    {
        if (!is_array($symbols)) {
            return is_string($symbols) ? [$symbols] : [];
        }

        $result = [];
        array_walk_recursive($symbols, function ($item) use (&$result) {
            if (is_string($item)) {
                $result[] = $item;
            } elseif (is_array($item) && isset($item['name'])) {
                $result[] = $item['name'];
            }
        });

        return array_unique($result);
    }
}
