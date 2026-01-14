<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectScan;
use App\Services\Projects\ChunkBuilder;
use App\Services\Projects\KnowledgeBaseBuilder;
use App\Services\Projects\ScannerService;
use Illuminate\Console\Command;

class RebuildKnowledgeBaseCommand extends Command
{
    protected $signature = 'kb:rebuild
                            {project : Project UUID to rebuild}
                            {--chunks : Also rebuild chunks from source files}
                            {--full : Full rebuild including file manifest}';

    protected $description = 'Rebuild the knowledge base output for a project';

    public function handle(
        ScannerService $scanner,
        ChunkBuilder   $chunkBuilder,
    ): int {
        $projectId = $this->argument('project');
        $rebuildChunks = $this->option('chunks');
        $fullRebuild = $this->option('full');

        $project = Project::find($projectId);

        if (!$project) {
            $this->error("Project not found: {$projectId}");
            return self::FAILURE;
        }
dd($project->hasLocalRepo());
        if (!$project->hasLocalRepo()) {
            $this->error("Project has no local repository. Run a scan first.");
            return self::FAILURE;
        }

        $this->info("Rebuilding knowledge base for: {$project->repo_full_name}");

        try {
            if ($fullRebuild) {
                $this->rebuildFull($project, $scanner, $chunkBuilder);
            } elseif ($rebuildChunks) {
                $this->rebuildChunks($project, $chunkBuilder);
            }

            $this->rebuildKbOutput($project);

            $this->info("\n✓ Knowledge base rebuilt successfully!");
            $this->line("  Scan ID: {$project->last_kb_scan_id}");
            $this->line("  Output: {$project->latest_kb_path}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Rebuild failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function rebuildFull(Project $project, ScannerService $scanner, ChunkBuilder $chunkBuilder): void
    {
        $this->line("\n[1/3] Scanning files...");

        $result = $scanner->scanDirectory($project, function ($processed, $total) {
            if ($processed % 100 === 0) {
                $this->output->write("\r  Processed {$processed}/{$total} files");
            }
        });

        $this->output->write("\r");
        $this->info("  Scanned {$result['stats']['total_files']} files");

        $scanner->persistManifest($project, $result['files']);
        $project->updateStats(
            $result['stats']['total_files'],
            $result['stats']['total_lines'],
            $result['stats']['total_bytes']
        );

        $this->rebuildChunks($project, $chunkBuilder);
    }

    private function rebuildChunks(Project $project, ChunkBuilder $chunkBuilder): void
    {
        $this->line("\n[2/3] Building chunks...");

        $result = $chunkBuilder->build($project, function ($processed, $total) {
            if ($processed % 50 === 0) {
                $this->output->write("\r  Processed {$processed}/{$total} files");
            }
        });

        $this->output->write("\r");
        $this->info("  Created {$result['total_chunks']} chunks");
    }

    private function rebuildKbOutput(Project $project): void
    {
        $this->line("\n[3/3] Building knowledge base output...");

        $scan = $project->latestScan();
        if (!$scan) {
            $scan = ProjectScan::create([
                'project_id' => $project->id,
                'status' => 'completed',
                'trigger' => 'rebuild',
                'commit_sha' => $project->last_commit_sha,
                'scanner_version' => '2.1.0',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        $builder = new KnowledgeBaseBuilder($project, $scan);
        $validation = $builder->build();

        $project->update([
            'scan_output_version' => '2.1.0',
            'last_kb_scan_id' => $builder->getScanId(),
        ]);

        if ($validation['is_valid']) {
            $this->info("  ✓ Validation passed");
        } else {
            $this->warn("  ⚠ Validation issues detected:");
            $this->line("    Missing in chunks: {$validation['missing_in_chunks']}");
            $this->line("    Orphaned chunks: {$validation['orphaned_chunks']}");
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Files', $validation['files_index_entries']],
                ['Chunks', $validation['chunks_count']],
                ['Coverage', "{$validation['coverage_percent']}%"],
            ]
        );
    }
}
