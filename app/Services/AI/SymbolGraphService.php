<?php

namespace App\Services\AI;

use App\DTOs\SymbolGraph;
use App\Models\Project;
use App\Services\Projects\KnowledgeBaseReader;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builds and queries the symbol relationship graph for a codebase.
 */
class SymbolGraphService
{
    private array $config;
    private array $relationshipWeights;

    public function __construct()
    {
        $this->config = config('retrieval.symbol_graph', []);
        $this->relationshipWeights = $this->config['relationship_types'] ?? [
            'imports' => 1.0,
            'extends' => 0.9,
            'implements' => 0.9,
            'uses_trait' => 0.8,
            'instantiates' => 0.7,
            'calls' => 0.6,
            'references' => 0.5,
        ];
    }

    /**
     * Build a symbol graph for a project.
     */
    public function buildGraph(Project $project): SymbolGraph
    {
        $startTime = microtime(true);

        // Check if KB exists before trying to create reader
        if (!$project->last_kb_scan_id) {
            Log::warning('SymbolGraphService: Cannot build graph - no KB scan ID', [
                'project_id' => $project->id,
            ]);
            return SymbolGraph::empty();
        }

        try {
            $reader = new KnowledgeBaseReader($project);
        } catch (\Throwable $e) {
            Log::warning('SymbolGraphService: Cannot build graph - KB reader failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return SymbolGraph::empty();
        }

        $nodes = [];
        $edges = [];
        $maxNodes = $this->config['max_nodes'] ?? 5000;
        $processedCount = 0;

        // Build nodes from files index
        foreach ($reader->getFilesIndexIterator() as $file) {
            if ($processedCount >= $maxNodes) {
                break;
            }

            $filePath = $file['file_path'];

            // Skip excluded or binary files
            if ($file['is_excluded'] ?? false || $file['is_binary'] ?? false) {
                continue;
            }

            $nodes[$filePath] = [
                'symbols_declared' => $file['symbols_declared'] ?? [],
                'symbols_used' => [],
                'imports' => $file['imports'] ?? [],
                'language' => $file['language'] ?? 'plaintext',
                'size_bytes' => $file['size_bytes'] ?? 0,
            ];

            $processedCount++;
        }

        // Enrich nodes with chunk-level symbol data
        foreach ($reader->getChunksIterator() as $chunk) {
            $filePath = $chunk['file_path'];

            if (!isset($nodes[$filePath])) {
                continue;
            }

            // Merge symbols_used from chunks
            $usedSymbols = $chunk['symbols_used'] ?? [];
            foreach ($usedSymbols as $usage) {
                $nodes[$filePath]['symbols_used'][] = $usage;
            }

            // Merge any additional imports from chunks
            $chunkImports = $chunk['imports'] ?? [];
            foreach ($chunkImports as $import) {
                if (!$this->importExists($nodes[$filePath]['imports'], $import)) {
                    $nodes[$filePath]['imports'][] = $import;
                }
            }
        }

        // Build edges from imports and symbol usage
        $symbolToFile = $this->buildSymbolIndex($nodes);

        foreach ($nodes as $sourcePath => $sourceInfo) {
            $edges[$sourcePath] = [];

            // Create edges from imports
            foreach ($sourceInfo['imports'] as $import) {
                $targetPath = $this->resolveImportToFile($import, $nodes, $sourcePath);
                if ($targetPath && $targetPath !== $sourcePath) {
                    $edges[$sourcePath][$targetPath] = [
                        'type' => 'imports',
                        'weight' => $this->relationshipWeights['imports'],
                    ];
                }
            }

            // Create edges from symbol usage
            foreach ($sourceInfo['symbols_used'] as $usage) {
                $symbolName = is_array($usage) ? ($usage['symbol'] ?? '') : $usage;
                $targetPaths = $symbolToFile[$symbolName] ?? [];

                foreach ($targetPaths as $targetPath) {
                    if ($targetPath !== $sourcePath && !isset($edges[$sourcePath][$targetPath])) {
                        $edges[$sourcePath][$targetPath] = [
                            'type' => 'references',
                            'weight' => $this->relationshipWeights['references'],
                        ];
                    }
                }
            }

            // Detect extends/implements/uses from declared symbols
            foreach ($sourceInfo['symbols_declared'] as $symbol) {
                $this->detectInheritanceEdges($symbol, $symbolToFile, $sourcePath, $edges);
            }
        }

        // Remove empty edge arrays
        $edges = array_filter($edges, fn($targets) => !empty($targets));

        $duration = (microtime(true) - $startTime) * 1000;

        Log::info('SymbolGraphService: Graph built', [
            'project_id' => $project->id,
            'nodes' => count($nodes),
            'edges' => array_sum(array_map('count', $edges)),
            'duration_ms' => round($duration, 2),
        ]);

        return new SymbolGraph(
            nodes: $nodes,
            edges: $edges,
            metadata: [
                'project_id' => $project->id,
                'built_at' => now()->toIso8601String(),
                'build_duration_ms' => round($duration, 2),
                'node_count' => count($nodes),
                'truncated' => $processedCount >= $maxNodes,
            ],
        );
    }

    /**
     * Get cached or build new symbol graph.
     */
    public function getGraph(Project $project): SymbolGraph
    {
        $cacheKey = $this->getCacheKey($project);

        if (config('retrieval.cache.enabled', true)) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                try {
                    return SymbolGraph::deserialize($cached);
                } catch (Exception $e) {
                    Log::warning('SymbolGraphService: Cache deserialization failed', [
                        'project_id' => $project->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $graph = $this->buildGraph($project);

        if (config('retrieval.cache.enabled', true) && $graph->getNodeCount() > 0) {
            $ttl = config('retrieval.cache.ttl.symbol_graph', 3600);
            Cache::put($cacheKey, $graph->serialize(), $ttl);
        }

        return $graph;
    }

    /**
     * Find all files that depend on a given file.
     *
     * @return array<string, array{type: string, weight: float}>
     */
    public function findDependents(Project $project, string $filePath): array
    {
        $graph = $this->getGraph($project);
        return $graph->getDependents($filePath);
    }

    /**
     * Find all files that a given file depends on.
     *
     * @return array<string, array{type: string, weight: float}>
     */
    public function findDependencies(Project $project, string $filePath): array
    {
        $graph = $this->getGraph($project);
        return $graph->getDependencies($filePath);
    }

    /**
     * Find files that define or use a symbol.
     *
     * @return array{declares: array<string>, uses: array<string>}
     */
    public function findBySymbol(Project $project, string $symbolName): array
    {
        $graph = $this->getGraph($project);

        return [
            'declares' => $graph->findBySymbol($symbolName),
            'uses' => $graph->findSymbolUsages($symbolName),
        ];
    }

    /**
     * Get the full dependency tree for a file.
     *
     * @return array<string, array{path: string, relationship: string, depth: int, weight: float}>
     */
    public function getDependencyTree(Project $project, string $filePath, int $maxDepth = 3): array
    {
        $graph = $this->getGraph($project);
        return $graph->getRelated($filePath, min($maxDepth, $this->config['max_depth'] ?? 5));
    }

    /**
     * Invalidate cached graph for a project.
     */
    public function invalidateCache(Project $project): void
    {
        $cacheKey = $this->getCacheKey($project);
        Cache::forget($cacheKey);
    }

    /**
     * Build an index of symbol names to file paths.
     *
     * @param array<string, array{symbols_declared: array}> $nodes
     * @return array<string, array<string>>
     */
    private function buildSymbolIndex(array $nodes): array
    {
        $index = [];

        foreach ($nodes as $filePath => $info) {
            foreach ($info['symbols_declared'] as $symbol) {
                $name = is_array($symbol) ? ($symbol['name'] ?? '') : $symbol;
                if (!empty($name)) {
                    $index[$name][] = $filePath;
                }
            }
        }

        return $index;
    }

    /**
     * Resolve an import statement to a target file path.
     *
     * @param array<string, array> $nodes
     */
    private function resolveImportToFile(array|string $import, array $nodes, string $sourcePath): ?string
    {
        $importPath = is_array($import) ? ($import['path'] ?? '') : $import;

        if (empty($importPath)) {
            return null;
        }

        // Handle PHP namespace imports (e.g., "App\Models\User")
        if (str_contains($importPath, '\\')) {
            $expectedPath = str_replace('\\', '/', $importPath) . '.php';

            // Try common Laravel patterns
            $patterns = [
                'app/' . str_replace('App/', '', $expectedPath),
                strtolower($expectedPath),
            ];

            foreach ($patterns as $pattern) {
                foreach (array_keys($nodes) as $nodePath) {
                    if (str_ends_with(strtolower($nodePath), strtolower($pattern))) {
                        return $nodePath;
                    }
                }
            }

            // Fallback: match by class name
            $className = basename(str_replace('\\', '/', $importPath));
            foreach (array_keys($nodes) as $nodePath) {
                if (pathinfo($nodePath, PATHINFO_FILENAME) === $className) {
                    return $nodePath;
                }
            }
        }

        // Handle JS/TS relative imports (e.g., "./User", "../components/Button")
        if (str_starts_with($importPath, '.') || str_starts_with($importPath, '@')) {
            $sourceDir = dirname($sourcePath);
            $resolved = $this->resolveRelativePath($sourceDir, $importPath);

            // Try with common extensions
            $extensions = ['', '.js', '.ts', '.vue', '.jsx', '.tsx'];
            foreach ($extensions as $ext) {
                $testPath = $resolved . $ext;
                if (isset($nodes[$testPath])) {
                    return $testPath;
                }

                // Try index file
                $indexPath = $resolved . '/index' . ($ext ?: '.js');
                if (isset($nodes[$indexPath])) {
                    return $indexPath;
                }
            }
        }

        return null;
    }

    /**
     * Resolve a relative import path.
     */
    private function resolveRelativePath(string $baseDir, string $relativePath): string
    {
        // Handle @ alias (common in Vue/React projects)
        if (str_starts_with($relativePath, '@/')) {
            return 'resources/js/' . substr($relativePath, 2);
        }

        $parts = explode('/', $baseDir);
        $relParts = explode('/', $relativePath);

        foreach ($relParts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            } elseif ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    /**
     * Detect inheritance relationships (extends, implements, uses trait).
     *
     * @param array<string, array<string, array{type: string, weight: float}>> $edges
     * @param array<string, array<string>> $symbolToFile
     */
    private function detectInheritanceEdges(
        array|string $symbol,
        array $symbolToFile,
        string $sourcePath,
        array &$edges
    ): void {
        if (!is_array($symbol)) {
            return;
        }

        $type = $symbol['type'] ?? '';

        // Only process class-like declarations
        if (!in_array($type, ['class', 'trait', 'interface'])) {
            return;
        }

        // Check for extends
        if (isset($symbol['extends'])) {
            $parentName = $symbol['extends'];
            $parentFiles = $symbolToFile[$parentName] ?? [];

            foreach ($parentFiles as $parentFile) {
                if ($parentFile !== $sourcePath) {
                    $edges[$sourcePath][$parentFile] = [
                        'type' => 'extends',
                        'weight' => $this->relationshipWeights['extends'],
                    ];
                }
            }
        }

        // Check for implements
        foreach ($symbol['implements'] ?? [] as $interface) {
            $interfaceFiles = $symbolToFile[$interface] ?? [];

            foreach ($interfaceFiles as $interfaceFile) {
                if ($interfaceFile !== $sourcePath) {
                    $edges[$sourcePath][$interfaceFile] = [
                        'type' => 'implements',
                        'weight' => $this->relationshipWeights['implements'],
                    ];
                }
            }
        }

        // Check for uses (traits)
        foreach ($symbol['uses'] ?? [] as $trait) {
            $traitFiles = $symbolToFile[$trait] ?? [];

            foreach ($traitFiles as $traitFile) {
                if ($traitFile !== $sourcePath) {
                    $edges[$sourcePath][$traitFile] = [
                        'type' => 'uses_trait',
                        'weight' => $this->relationshipWeights['uses_trait'],
                    ];
                }
            }
        }
    }

    /**
     * Check if an import already exists in the list.
     *
     * @param array<array{path: string}> $imports
     */
    private function importExists(array $imports, array|string $newImport): bool
    {
        $newPath = is_array($newImport) ? ($newImport['path'] ?? '') : $newImport;

        foreach ($imports as $import) {
            $existingPath = is_array($import) ? ($import['path'] ?? '') : $import;
            if ($existingPath === $newPath) {
                return true;
            }
        }

        return false;
    }

    private function getCacheKey(Project $project): string
    {
        $prefix = config('retrieval.cache.prefix', 'retrieval');
        $scanId = $project->last_kb_scan_id ?? 'none';
        return "{$prefix}:symbol_graph:{$project->id}:{$scanId}";
    }
}
