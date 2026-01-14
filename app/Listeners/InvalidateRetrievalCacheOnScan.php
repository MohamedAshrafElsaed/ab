<?php

namespace App\Listeners;

use App\Events\ProjectScanCompleted;
use App\Services\AI\RetrievalCacheService;
use App\Services\AI\RouteAnalyzerService;
use App\Services\AI\SymbolGraphService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates and optionally warms up the retrieval cache when a project scan completes.
 */
class InvalidateRetrievalCacheOnScan implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly RetrievalCacheService $cacheService,
        private readonly SymbolGraphService $symbolGraphService,
        private readonly RouteAnalyzerService $routeAnalyzerService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ProjectScanCompleted $event): void
    {
        $project = $event->project;

        Log::info('InvalidateRetrievalCacheOnScan: Processing', [
            'project_id' => $project->id,
            'scan_id' => $event->scanId,
        ]);

        // Invalidate existing cache
        $this->cacheService->invalidateOnScan($project);

        // Optionally warm up the cache immediately
        if (config('retrieval.cache.warmup_on_scan', true)) {
            try {
                $this->cacheService->warmUp(
                    $project,
                    $this->symbolGraphService,
                    $this->routeAnalyzerService
                );

                Log::info('InvalidateRetrievalCacheOnScan: Cache warmed up', [
                    'project_id' => $project->id,
                ]);
            } catch (\Exception $e) {
                Log::warning('InvalidateRetrievalCacheOnScan: Warmup failed', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(ProjectScanCompleted $event, \Throwable $exception): void
    {
        Log::error('InvalidateRetrievalCacheOnScan: Failed', [
            'project_id' => $event->project->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
