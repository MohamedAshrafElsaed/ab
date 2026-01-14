<?php

namespace App\DTOs;

/**
 * Represents the symbol relationship graph for a codebase.
 */
readonly class SymbolGraph
{
    /**
     * @param array<string, array{
     *     symbols_declared: array<array{type: string, name: string, line: int}>,
     *     symbols_used: array<array{symbol: string, line: int}>,
     *     imports: array<array{type: string, path: string, line: int}>,
     *     language: string,
     *     size_bytes: int
     * }> $nodes File path => symbol information
     * @param array<string, array<string, array{type: string, weight: float}>> $edges From file => [to file => relationship]
     * @param array<string, mixed> $metadata Build info, stats
     */
    public function __construct(
        public array $nodes,
        public array $edges,
        public array $metadata = [],
    ) {}

    /**
     * Create an empty graph.
     */
    public static function empty(): self
    {
        return new self(
            nodes: [],
            edges: [],
            metadata: ['empty' => true, 'built_at' => now()->toIso8601String()],
        );
    }

    /**
     * Get files related to a given file within specified depth.
     *
     * @return array<string, array{path: string, relationship: string, depth: int, weight: float}>
     */
    public function getRelated(string $filePath, int $depth = 1): array
    {
        if (!isset($this->nodes[$filePath])) {
            return [];
        }

        $related = [];
        $visited = [$filePath => true];
        $queue = [['path' => $filePath, 'depth' => 0]];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $currentPath = $current['path'];
            $currentDepth = $current['depth'];

            if ($currentDepth >= $depth) {
                continue;
            }

            // Get outgoing edges (files this file depends on)
            $outgoing = $this->edges[$currentPath] ?? [];
            foreach ($outgoing as $targetPath => $relationship) {
                if (!isset($visited[$targetPath])) {
                    $visited[$targetPath] = true;
                    $related[$targetPath] = [
                        'path' => $targetPath,
                        'relationship' => $relationship['type'],
                        'depth' => $currentDepth + 1,
                        'weight' => $relationship['weight'],
                        'direction' => 'outgoing',
                    ];
                    $queue[] = ['path' => $targetPath, 'depth' => $currentDepth + 1];
                }
            }

            // Get incoming edges (files that depend on this file)
            foreach ($this->edges as $sourcePath => $targets) {
                if (isset($targets[$currentPath]) && !isset($visited[$sourcePath])) {
                    $visited[$sourcePath] = true;
                    $related[$sourcePath] = [
                        'path' => $sourcePath,
                        'relationship' => $targets[$currentPath]['type'] . '_by',
                        'depth' => $currentDepth + 1,
                        'weight' => $targets[$currentPath]['weight'],
                        'direction' => 'incoming',
                    ];
                    $queue[] = ['path' => $sourcePath, 'depth' => $currentDepth + 1];
                }
            }
        }

        // Sort by depth, then by weight
        uasort($related, function ($a, $b) {
            if ($a['depth'] !== $b['depth']) {
                return $a['depth'] <=> $b['depth'];
            }
            return $b['weight'] <=> $a['weight'];
        });

        return $related;
    }

    /**
     * Find the shortest path between two files.
     *
     * @return array<string>|null Ordered list of files in path, or null if no path exists
     */
    public function findPathBetween(string $from, string $to): ?array
    {
        if (!isset($this->nodes[$from]) || !isset($this->nodes[$to])) {
            return null;
        }

        if ($from === $to) {
            return [$from];
        }

        // BFS to find shortest path
        $visited = [$from => true];
        $parent = [$from => null];
        $queue = [$from];

        while (!empty($queue)) {
            $current = array_shift($queue);

            // Check outgoing edges
            $outgoing = $this->edges[$current] ?? [];
            foreach (array_keys($outgoing) as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $parent[$neighbor] = $current;

                    if ($neighbor === $to) {
                        return $this->reconstructPath($parent, $from, $to);
                    }

                    $queue[] = $neighbor;
                }
            }

            // Check incoming edges (bidirectional search)
            foreach ($this->edges as $source => $targets) {
                if (isset($targets[$current]) && !isset($visited[$source])) {
                    $visited[$source] = true;
                    $parent[$source] = $current;

                    if ($source === $to) {
                        return $this->reconstructPath($parent, $from, $to);
                    }

                    $queue[] = $source;
                }
            }
        }

        return null;
    }

    /**
     * Get a cluster of strongly related files.
     *
     * @return array<string, array{path: string, cluster_score: float}>
     */
    public function getCluster(string $filePath, int $maxSize = 20): array
    {
        if (!isset($this->nodes[$filePath])) {
            return [];
        }

        // Get all related files with their scores
        $related = $this->getRelated($filePath, 3);

        // Score each file based on relationship depth and weight
        $scored = [];
        foreach ($related as $path => $info) {
            $depthFactor = 1 / ($info['depth'] + 1);
            $clusterScore = $info['weight'] * $depthFactor;

            $scored[$path] = [
                'path' => $path,
                'cluster_score' => $clusterScore,
            ];
        }

        // Sort by cluster score and limit
        uasort($scored, fn($a, $b) => $b['cluster_score'] <=> $a['cluster_score']);

        return array_slice($scored, 0, $maxSize, true);
    }

    /**
     * Get all files that depend on a given file.
     *
     * @return array<string, array{type: string, weight: float}>
     */
    public function getDependents(string $filePath): array
    {
        $dependents = [];

        foreach ($this->edges as $sourcePath => $targets) {
            if (isset($targets[$filePath])) {
                $dependents[$sourcePath] = $targets[$filePath];
            }
        }

        return $dependents;
    }

    /**
     * Get all files that a given file depends on.
     *
     * @return array<string, array{type: string, weight: float}>
     */
    public function getDependencies(string $filePath): array
    {
        return $this->edges[$filePath] ?? [];
    }

    /**
     * Get files that declare a specific symbol.
     *
     * @return array<string>
     */
    public function findBySymbol(string $symbolName): array
    {
        $results = [];

        foreach ($this->nodes as $filePath => $info) {
            foreach ($info['symbols_declared'] ?? [] as $symbol) {
                $name = is_array($symbol) ? ($symbol['name'] ?? '') : $symbol;
                if (strcasecmp($name, $symbolName) === 0) {
                    $results[] = $filePath;
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Get files that use/reference a specific symbol.
     *
     * @return array<string>
     */
    public function findSymbolUsages(string $symbolName): array
    {
        $results = [];

        foreach ($this->nodes as $filePath => $info) {
            foreach ($info['symbols_used'] ?? [] as $usage) {
                $symbol = is_array($usage) ? ($usage['symbol'] ?? '') : $usage;
                if (stripos($symbol, $symbolName) !== false) {
                    $results[] = $filePath;
                    break;
                }
            }

            // Also check imports
            foreach ($info['imports'] ?? [] as $import) {
                $path = is_array($import) ? ($import['path'] ?? '') : $import;
                if (stripos($path, $symbolName) !== false) {
                    $results[] = $filePath;
                    break;
                }
            }
        }

        return array_unique($results);
    }

    /**
     * Get statistics about the graph.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $edgeCount = 0;
        foreach ($this->edges as $targets) {
            $edgeCount += count($targets);
        }

        $symbolCount = 0;
        foreach ($this->nodes as $info) {
            $symbolCount += count($info['symbols_declared'] ?? []);
        }

        return [
            'node_count' => count($this->nodes),
            'edge_count' => $edgeCount,
            'symbol_count' => $symbolCount,
            'avg_dependencies' => count($this->nodes) > 0
                ? round($edgeCount / count($this->nodes), 2)
                : 0,
        ];
    }

    /**
     * Check if the graph contains a file.
     */
    public function hasFile(string $filePath): bool
    {
        return isset($this->nodes[$filePath]);
    }

    /**
     * Get node count.
     */
    public function getNodeCount(): int
    {
        return count($this->nodes);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'metadata' => $this->metadata,
            'stats' => $this->getStats(),
        ];
    }

    /**
     * Serialize for caching.
     */
    public function serialize(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Deserialize from cache.
     */
    public static function deserialize(string $data): self
    {
        $array = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            nodes: $array['nodes'] ?? [],
            edges: $array['edges'] ?? [],
            metadata: $array['metadata'] ?? [],
        );
    }

    /**
     * Reconstruct path from BFS parent map.
     *
     * @param array<string, ?string> $parent
     * @return array<string>
     */
    private function reconstructPath(array $parent, string $from, string $to): array
    {
        $path = [];
        $current = $to;

        while ($current !== null) {
            array_unshift($path, $current);
            $current = $parent[$current] ?? null;
        }

        return $path;
    }
}
