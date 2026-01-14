<?php


namespace App\Services\AI;

use App\DTOs\SymbolGraph;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Caching layer for retrieval services.
 */
class RetrievalCacheService
{
    private array $config;
    private string $prefix;

    public function __construct()
    {
        $this->config = config('retrieval.cache', []);
        $this->prefix = $this->config['prefix'] ?? 'retrieval';
    }

    /**
     * Cache a symbol graph for a project.
     */
    public function cacheSymbolGraph(Project $project, SymbolGraph $graph): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $key = $this->getSymbolGraphKey($project);
        $ttl = $this->config['ttl']['symbol_graph'] ?? 3600;

        try {
            Cache::put($key, $graph->serialize(), $ttl);

            Log::debug('RetrievalCache: Symbol graph cached', [
                'project_id' => $project->id,
                'key' => $key,
                'nodes' => $graph->getNodeCount(),
                'ttl' => $ttl,
            ]);
        } catch (\Exception $e) {
            Log::warning('RetrievalCache: Failed to cache symbol graph', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get cached symbol graph for a project.
     */
    public function getCachedSymbolGraph(Project $project): ?SymbolGraph
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $key = $this->getSymbolGraphKey($project);

        try {
            $data = Cache::get($key);

            if ($data === null) {
                return null;
            }

            return SymbolGraph::deserialize($data);
        } catch (\Exception $e) {
            Log::warning('RetrievalCache: Failed to deserialize symbol graph', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            // Remove corrupted cache entry
            Cache::forget($key);

            return null;
        }
    }

    /**
     * Cache routes for a project.
     *
     * @param array<int, array{uri: string, method: string, controller: ?string}> $routes
     */
    public function cacheRoutes(Project $project, array $routes): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $key = $this->getRoutesKey($project);
        $ttl = $this->config['ttl']['routes'] ?? 1800;

        Cache::put($key, $routes, $ttl);

        Log::debug('RetrievalCache: Routes cached', [
            'project_id' => $project->id,
            'key' => $key,
            'route_count' => count($routes),
            'ttl' => $ttl,
        ]);
    }

    /**
     * Get cached routes for a project.
     *
     * @return array<int, array{uri: string, method: string, controller: ?string}>|null
     */
    public function getCachedRoutes(Project $project): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $key = $this->getRoutesKey($project);
        return Cache::get($key);
    }

    /**
     * Invalidate all cached data for a project (e.g., after a new scan).
     */
    public function invalidateOnScan(Project $project): void
    {
        $keys = [
            $this->getSymbolGraphKey($project),
            $this->getRoutesKey($project),
            $this->getRetrievalResultsKey($project),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Also invalidate with wildcard pattern for any scan-specific keys
        $pattern = "{$this->prefix}:*:{$project->id}:*";
        $this->forgetByPattern($pattern);

        Log::info('RetrievalCache: Invalidated cache for project', [
            'project_id' => $project->id,
            'keys_cleared' => count($keys),
        ]);
    }

    /**
     * Invalidate symbol graph cache.
     */
    public function invalidateSymbolGraph(Project $project): void
    {
        $key = $this->getSymbolGraphKey($project);
        Cache::forget($key);

        Log::debug('RetrievalCache: Symbol graph cache invalidated', [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Invalidate routes cache.
     */
    public function invalidateRoutes(Project $project): void
    {
        $key = $this->getRoutesKey($project);
        Cache::forget($key);

        Log::debug('RetrievalCache: Routes cache invalidated', [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Get cache statistics for a project.
     *
     * @return array{symbol_graph: bool, routes: bool, hits: int, misses: int}
     */
    public function getStats(Project $project): array
    {
        return [
            'symbol_graph' => Cache::has($this->getSymbolGraphKey($project)),
            'routes' => Cache::has($this->getRoutesKey($project)),
            'enabled' => $this->isEnabled(),
        ];
    }

    /**
     * Warm up cache for a project.
     */
    public function warmUp(
        Project              $project,
        SymbolGraphService   $symbolGraphService,
        RouteAnalyzerService $routeAnalyzer
    ): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        Log::info('RetrievalCache: Warming up cache', ['project_id' => $project->id]);

        // Build and cache symbol graph
        $graph = $symbolGraphService->buildGraph($project);
        $this->cacheSymbolGraph($project, $graph);

        // Cache routes
        $routes = $routeAnalyzer->getRoutes($project);
        $this->cacheRoutes($project, $routes->toArray());

        Log::info('RetrievalCache: Cache warmed up', [
            'project_id' => $project->id,
            'symbol_nodes' => $graph->getNodeCount(),
            'routes' => $routes->count(),
        ]);
    }

    /**
     * Check if caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get cache key for symbol graph.
     */
    private function getSymbolGraphKey(Project $project): string
    {
        $scanId = $project->last_kb_scan_id ?? 'none';
        return "{$this->prefix}:symbol_graph:{$project->id}:{$scanId}";
    }

    /**
     * Get cache key for routes.
     */
    private function getRoutesKey(Project $project): string
    {
        $scanId = $project->last_kb_scan_id ?? 'none';
        return "{$this->prefix}:routes:{$project->id}:{$scanId}";
    }

    /**
     * Get cache key for retrieval results.
     */
    private function getRetrievalResultsKey(Project $project): string
    {
        $scanId = $project->last_kb_scan_id ?? 'none';
        return "{$this->prefix}:results:{$project->id}:{$scanId}";
    }

    /**
     * Forget cache entries by pattern.
     *
     * Note: This only works with cache drivers that support tags or pattern deletion.
     * For file/database drivers, it may not work as expected.
     */
    private function forgetByPattern(string $pattern): void
    {
        $driver = config('cache.default');

        // Redis and Memcached support pattern deletion
        if (in_array($driver, ['redis', 'memcached'])) {
            try {
                $store = Cache::getStore();

                if ($driver === 'redis' && method_exists($store, 'getRedis')) {
                    $redis = $store->getRedis();
                    $prefix = config('cache.prefix', 'laravel');
                    $keys = $redis->keys("{$prefix}:{$pattern}");

                    if (!empty($keys)) {
                        $redis->del($keys);
                    }
                }
            } catch (\Exception $e) {
                Log::debug('RetrievalCache: Pattern deletion not supported', [
                    'driver' => $driver,
                    'pattern' => $pattern,
                ]);
            }
        }
    }
}
