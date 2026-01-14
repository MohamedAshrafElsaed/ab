<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryOptimizationService
{
    /**
     * Default cache TTL in seconds.
     */
    protected int $defaultCacheTtl;

    /**
     * Slow query threshold in milliseconds.
     */
    protected float $slowQueryThreshold;

    public function __construct()
    {
        $this->defaultCacheTtl = config('database.cache_ttl', 3600);
        $this->slowQueryThreshold = config('database.slow_query_threshold', 1000);
    }

    /**
     * Execute a query with caching.
     *
     * @param  callable(): mixed  $queryCallback
     */
    public function cached(string $cacheKey, callable $queryCallback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->defaultCacheTtl;

        return Cache::remember($cacheKey, $ttl, $queryCallback);
    }

    /**
     * Execute a query and invalidate related cache.
     *
     * @param  callable(): mixed  $queryCallback
     * @param  array<string>  $cacheKeys
     */
    public function executeAndInvalidate(callable $queryCallback, array $cacheKeys): mixed
    {
        $result = $queryCallback();

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        return $result;
    }

    /**
     * Invalidate cache by pattern.
     */
    public function invalidateByPattern(string $pattern): void
    {
        // For Redis cache driver
        if (config('cache.default') === 'redis') {
            $keys = Cache::getRedis()->keys(config('cache.prefix') . ':' . $pattern);
            foreach ($keys as $key) {
                $key = str_replace(config('cache.prefix') . ':', '', $key);
                Cache::forget($key);
            }
        }
    }

    /**
     * Log slow queries.
     */
    public function enableSlowQueryLogging(): void
    {
        DB::listen(function ($query) {
            if ($query->time > $this->slowQueryThreshold) {
                Log::channel('slow-queries')->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }

    /**
     * Optimize a query by adding appropriate indexes hint.
     *
     * @return array{query: string, suggested_indexes: array<string>}
     */
    public function analyzeQuery(string $sql): array
    {
        $suggestions = [];

        // Check for common patterns that might benefit from indexes
        if (preg_match('/WHERE.*?(\w+)\s*=/', $sql, $matches)) {
            $suggestions[] = "Consider index on column: {$matches[1]}";
        }

        if (preg_match('/ORDER BY\s+(\w+)/i', $sql, $matches)) {
            $suggestions[] = "Consider index on column: {$matches[1]} for ORDER BY";
        }

        if (preg_match('/JOIN.*?ON.*?(\w+)\.(\w+)\s*=\s*(\w+)\.(\w+)/i', $sql, $matches)) {
            $suggestions[] = "Consider index on join columns: {$matches[1]}.{$matches[2]} and {$matches[3]}.{$matches[4]}";
        }

        return [
            'query' => $sql,
            'suggested_indexes' => $suggestions,
        ];
    }

    /**
     * Get cache key for a project-related query.
     */
    public function projectCacheKey(string $projectId, string $operation): string
    {
        return "project:{$projectId}:{$operation}";
    }

    /**
     * Get cache key for a user-related query.
     */
    public function userCacheKey(int $userId, string $operation): string
    {
        return "user:{$userId}:{$operation}";
    }

    /**
     * Get cache key for a conversation-related query.
     */
    public function conversationCacheKey(string $conversationId, string $operation): string
    {
        return "conversation:{$conversationId}:{$operation}";
    }

    /**
     * Batch invalidate project-related caches.
     */
    public function invalidateProjectCache(string $projectId): void
    {
        $operations = ['files', 'chunks', 'stats', 'context', 'conversations'];

        foreach ($operations as $operation) {
            Cache::forget($this->projectCacheKey($projectId, $operation));
        }
    }

    /**
     * Batch invalidate user-related caches.
     */
    public function invalidateUserCache(int $userId): void
    {
        $operations = ['projects', 'storage', 'conversations'];

        foreach ($operations as $operation) {
            Cache::forget($this->userCacheKey($userId, $operation));
        }
    }

    /**
     * Get database statistics for monitoring.
     *
     * @return array<string, mixed>
     */
    public function getDatabaseStats(): array
    {
        $stats = [];

        // Table sizes
        $tables = ['projects', 'project_files', 'project_file_chunks', 'agent_conversations', 'agent_messages', 'execution_plans', 'file_executions'];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $stats['tables'][$table] = [
                'count' => $count,
            ];
        }

        return $stats;
    }

    /**
     * Optimize project file chunks query with FULLTEXT.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\ProjectFileChunk>
     */
    public function optimizedChunkSearch(string $projectId, string $searchTerm): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\ProjectFileChunk::query()
            ->where('project_id', $projectId)
            ->whereRaw('MATCH(content, symbols_declared, symbols_used) AGAINST(? IN NATURAL LANGUAGE MODE)', [$searchTerm])
            ->orderByRaw('MATCH(content, symbols_declared, symbols_used) AGAINST(? IN NATURAL LANGUAGE MODE) DESC', [$searchTerm]);
    }
}
