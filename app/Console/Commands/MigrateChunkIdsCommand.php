<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Projects\Concerns\HasDeterministicChunkId;
use App\Services\Projects\KnowledgeBaseBuilder;
use App\Services\Projects\KnowledgeBaseReader;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateChunkIdsCommand extends Command
{
    use HasDeterministicChunkId;

    protected $signature = 'kb:migrate-chunk-ids
                            {--project= : Specific project ID to migrate}
                            {--dry-run : Show what would be changed without making changes}
                            {--validate : Only validate, don\'t migrate}
                            {--rebuild-kb : Rebuild the knowledge base output after migration}';

    protected $description = 'Migrate chunk IDs to the new deterministic format and optionally rebuild KB output';

    public function handle(): int
    {
        $projectId = $this->option('project');
        $dryRun = $this->option('dry-run');
        $validateOnly = $this->option('validate');
        $rebuildKb = $this->option('rebuild-kb');

        if ($validateOnly) {
            return $this->validateProjects($projectId);
        }

        $projects = $projectId
            ? Project::where('id', $projectId)->get()
            : Project::where('status', 'ready')->get();

        if ($projects->isEmpty()) {
            $this->warn('No projects found to migrate.');
            return 0;
        }

        $this->info("Found {$projects->count()} project(s) to process.");

        $totalMigrated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($projects as $project) {
            $this->line("\nProcessing project {$project->id}: {$project->repo_full_name}");

            try {
                $result = $this->migrateProject($project, $dryRun);
                $totalMigrated += $result['migrated'];
                $totalSkipped += $result['skipped'];

                if ($result['migrated'] > 0) {
                    $this->info("  Migrated {$result['migrated']} chunks, skipped {$result['skipped']}");
                } else {
                    $this->line("  All {$result['skipped']} chunks already in new format");
                }

                // Optionally rebuild KB
                if ($rebuildKb && !$dryRun && $result['migrated'] > 0) {
                    $this->rebuildKnowledgeBase($project);
                }

            } catch (Exception $e) {
                $totalErrors++;
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Migration complete:");
        $this->line("  Total migrated: {$totalMigrated}");
        $this->line("  Total skipped: {$totalSkipped}");
        $this->line("  Total errors: {$totalErrors}");

        if ($dryRun) {
            $this->warn("\nThis was a dry run. No changes were made.");
        }

        return $totalErrors > 0 ? 1 : 0;
    }

    private function validateProjects(?int $projectId): int
    {
        $projects = $projectId
            ? Project::where('id', $projectId)->get()
            : Project::where('status', 'ready')->whereNotNull('last_kb_scan_id')->get();

        if ($projects->isEmpty()) {
            $this->warn('No projects with knowledge bases found.');
            return 0;
        }

        $this->info("Validating {$projects->count()} project(s)...\n");

        $valid = 0;
        $invalid = 0;

        foreach ($projects as $project) {
            $this->line("Project {$project->id}: {$project->repo_full_name}");

            if (!$project->last_kb_scan_id) {
                $this->warn("  No KB scan ID set");
                $invalid++;
                continue;
            }

            try {
                $reader = new KnowledgeBaseReader($project);
                $result = $reader->validate();

                if ($result['is_valid']) {
                    $this->info("  ✓ Valid");
                    $this->line("    Scan: {$result['scan_id']}");
                    $this->line("    Files: {$result['files_count']}, Chunks: {$result['chunks_count']}");
                    $valid++;
                } else {
                    $this->error("  ✗ Invalid");
                    $this->line("    Missing in chunks: {$result['missing_in_chunks']}");
                    $this->line("    Orphaned chunks: {$result['orphaned_chunks']}");
                    $invalid++;
                }
            } catch (Exception $e) {
                $this->error("  Error: {$e->getMessage()}");
                $invalid++;
            }

            $this->newLine();
        }

        $this->info("Validation complete: {$valid} valid, {$invalid} invalid");

        return $invalid > 0 ? 1 : 0;
    }

    private function migrateProject(Project $project, bool $dryRun): array
    {
        $migrated = 0;
        $skipped = 0;

        // Get all chunks with their associated file SHA1
        $chunks = DB::table('project_file_chunks as c')
            ->leftJoin('project_files as f', function ($join) {
                $join->on('c.path', '=', 'f.path')
                    ->on('c.project_id', '=', 'f.project_id');
            })
            ->where('c.project_id', $project->id)
            ->select('c.id', 'c.chunk_id', 'c.path', 'c.start_line', 'c.end_line', 'c.sha1', 'f.sha1 as file_sha1')
            ->cursor();

        $updates = [];

        foreach ($chunks as $chunk) {
            // Use file SHA1 from files table, fallback to chunk's SHA1
            $fileSha1 = $chunk->file_sha1 ?? $chunk->sha1;

            if (!$fileSha1) {
                $this->warn("  Skipping chunk {$chunk->id}: missing SHA1");
                $skipped++;
                continue;
            }

            // Check if already in new format
            if (self::isValidChunkIdFormat($chunk->chunk_id)) {
                // Verify it's correct
                $expected = self::generateChunkId(
                    $chunk->path,
                    $fileSha1,
                    $chunk->start_line,
                    $chunk->end_line
                );

                if ($chunk->chunk_id === $expected) {
                    $skipped++;
                    continue;
                }
            }

            // Generate new chunk ID using trait method
            $newChunkId = self::generateChunkId(
                $chunk->path,
                $fileSha1,
                $chunk->start_line,
                $chunk->end_line
            );

            if ($chunk->chunk_id !== $newChunkId) {
                $updates[] = [
                    'id' => $chunk->id,
                    'old_chunk_id' => $chunk->chunk_id,
                    'new_chunk_id' => $newChunkId,
                ];
                $migrated++;
            } else {
                $skipped++;
            }
        }

        // Apply updates
        if (!$dryRun && !empty($updates)) {
            DB::transaction(function () use ($updates) {
                foreach ($updates as $update) {
                    DB::table('project_file_chunks')
                        ->where('id', $update['id'])
                        ->update([
                            'old_chunk_id' => $update['old_chunk_id'],
                            'chunk_id' => $update['new_chunk_id'],
                            'updated_at' => now(),
                        ]);
                }
            });
        }

        return ['migrated' => $migrated, 'skipped' => $skipped];
    }

    private function rebuildKnowledgeBase(Project $project): void
    {
        $this->line("  Rebuilding knowledge base...");

        $scan = $project->latestScan();
        if (!$scan) {
            $this->warn("  No scan record found, skipping KB rebuild");
            return;
        }

        try {
            $builder = new KnowledgeBaseBuilder($project, $scan);
            $validation = $builder->build();

            $project->setLastKbScanId($builder->getScanId());

            if ($validation['is_valid']) {
                $this->info("  KB rebuilt successfully: {$builder->getScanId()}");
                $this->line("    Files: {$validation['files_index_entries']}, Chunks: {$validation['chunks_count']}");
            } else {
                $this->warn("  KB rebuilt with validation issues:");
                $this->line("    Missing: {$validation['missing_in_chunks']}, Orphaned: {$validation['orphaned_chunks']}");
            }
        } catch (Exception $e) {
            $this->error("  KB rebuild failed: {$e->getMessage()}");
        }
    }
}
