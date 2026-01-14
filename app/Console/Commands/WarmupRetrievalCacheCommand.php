<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\AI\RetrievalCacheService;
use App\Services\AI\RouteAnalyzerService;
use App\Services\AI\SymbolGraphService;
use Exception;
use Illuminate\Console\Command;

class WarmupRetrievalCacheCommand extends Command
{
    protected $signature = 'retrieval:warmup
                            {--project= : Specific project ID to warm up}
                            {--all : Warm up all ready projects}
                            {--stats : Show cache statistics only}';

    protected $description = 'Warm up the retrieval cache (symbol graph, routes) for projects';

    public function __construct(
        private readonly SymbolGraphService $symbolGraphService,
        private readonly RouteAnalyzerService $routeAnalyzerService,
        private readonly RetrievalCacheService $cacheService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('stats')) {
            return $this->showStats();
        }

        $projects = $this->getProjects();

        if ($projects->isEmpty()) {
            $this->warn('No projects found to warm up.');
            return 0;
        }

        $this->info("Warming up cache for {$projects->count()} project(s)...\n");

        $success = 0;
        $failed = 0;

        foreach ($projects as $project) {
            $this->line("Project {$project->id}: {$project->repo_full_name}");

            try {
                $startTime = microtime(true);

                // Warm up cache
                $this->cacheService->warmUp(
                    $project,
                    $this->symbolGraphService,
                    $this->routeAnalyzerService
                );

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                // Get stats
                $graph = $this->symbolGraphService->getGraph($project);
                $routes = $this->routeAnalyzerService->getRoutes($project);

                $this->info("  ✓ Cached in {$duration}ms");
                $this->line("    Symbol nodes: {$graph->getNodeCount()}");
                $this->line("    Routes: {$routes->count()}");

                $success++;

            } catch (Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                $failed++;
            }

            $this->newLine();
        }

        $this->info("Warmup complete: {$success} succeeded, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }

    private function getProjects()
    {
        $projectId = $this->option('project');

        if ($projectId) {
            return Project::where('id', $projectId)->get();
        }

        if ($this->option('all')) {
            return Project::where('status', 'ready')
                ->whereNotNull('last_kb_scan_id')
                ->get();
        }

        // Default: interactive selection
        $projects = Project::where('status', 'ready')
            ->whereNotNull('last_kb_scan_id')
            ->get();

        if ($projects->isEmpty()) {
            return collect();
        }

        if ($projects->count() === 1) {
            return $projects;
        }

        $choices = $projects->mapWithKeys(fn($p) => [$p->id => "{$p->id}: {$p->repo_full_name}"]);
        $choices->prepend('All projects', 'all');

        $selected = $this->choice(
            'Select project to warm up',
            $choices->toArray(),
            'all'
        );

        if ($selected === 'all') {
            return $projects;
        }

        return $projects->where('id', $selected);
    }

    private function showStats(): int
    {
        $projects = Project::where('status', 'ready')
            ->whereNotNull('last_kb_scan_id')
            ->get();

        if ($projects->isEmpty()) {
            $this->warn('No projects with knowledge bases found.');
            return 0;
        }

        $this->info("Cache Statistics\n");

        $headers = ['Project ID', 'Repository', 'Symbol Graph', 'Routes', 'Enabled'];
        $rows = [];

        foreach ($projects as $project) {
            $stats = $this->cacheService->getStats($project);

            $rows[] = [
                $project->id,
                $project->repo_full_name,
                $stats['symbol_graph'] ? '✓ Cached' : '✗ Missing',
                $stats['routes'] ? '✓ Cached' : '✗ Missing',
                $stats['enabled'] ? 'Yes' : 'No',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
