<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

class MigrateKnowledgeBasePathCommand extends Command
{
    protected $signature = 'kb:migrate-paths {--dry-run : Show what would be migrated without making changes}';
    protected $description = 'Migrate KB data from old path (storage/app/project_kb) to unified path (storage/app/projects/{id}/knowledge/scans)';

    public function handle(): int
    {
        $oldBasePath = storage_path('app/project_kb');
        $dryRun = $this->option('dry-run');

        if (!is_dir($oldBasePath)) {
            $this->info('No legacy KB data found at ' . $oldBasePath);
            return self::SUCCESS;
        }

        $this->info($dryRun ? '[DRY RUN] Scanning for KB data to migrate...' : 'Migrating KB data...');

        $migrated = 0;
        $errors = 0;

        foreach (scandir($oldBasePath) as $projectId) {
            if ($projectId === '.' || $projectId === '..') continue;
            if (!is_numeric($projectId)) continue;

            $oldProjectPath = $oldBasePath . '/' . $projectId;
            if (!is_dir($oldProjectPath)) continue;

            $project = Project::find($projectId);
            if (!$project) {
                $this->warn("  Project {$projectId} not found in database, skipping");
                continue;
            }

            $newScansPath = $project->kb_base_path;

            $this->line("  Project {$projectId}: {$project->repo_full_name}");

            foreach (scandir($oldProjectPath) as $scanId) {
                if ($scanId === '.' || $scanId === '..') continue;
                if (!str_starts_with($scanId, 'scan_')) continue;

                $oldScanPath = $oldProjectPath . '/' . $scanId;
                $newScanPath = $newScansPath . '/' . $scanId;

                if (!is_dir($oldScanPath)) continue;

                if (is_dir($newScanPath)) {
                    $this->line("    Skip {$scanId} (already exists at new location)");
                    continue;
                }

                if ($dryRun) {
                    $this->line("    Would migrate {$scanId}");
                    $this->line("      From: {$oldScanPath}");
                    $this->line("      To:   {$newScanPath}");
                } else {
                    if (!is_dir($newScansPath)) {
                        mkdir($newScansPath, 0755, true);
                    }

                    if (rename($oldScanPath, $newScanPath)) {
                        $this->info("    Migrated {$scanId}");
                        $migrated++;
                    } else {
                        $this->error("    Failed to migrate {$scanId}");
                        $errors++;
                    }
                }
            }

            if (!$dryRun && is_dir($oldProjectPath) && count(scandir($oldProjectPath)) === 2) {
                rmdir($oldProjectPath);
                $this->line("    Removed empty directory: {$oldProjectPath}");
            }
        }

        if (!$dryRun && is_dir($oldBasePath) && count(scandir($oldBasePath)) === 2) {
            rmdir($oldBasePath);
            $this->info("Removed empty legacy directory: {$oldBasePath}");
        }

        $this->newLine();
        if ($dryRun) {
            $this->info('Dry run complete. Run without --dry-run to apply changes.');
        } else {
            $this->info("Migration complete. Migrated: {$migrated}, Errors: {$errors}");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
