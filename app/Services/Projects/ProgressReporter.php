<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectScan;

class ProgressReporter
{
    private array $stages;
    private array $stageWeights;
    private int $totalWeight;

    public function __construct()
    {
        $this->stages = config('projects.pipeline_stages', []);
        $this->stageWeights = [];
        $this->totalWeight = 0;

        foreach ($this->stages as $key => $stage) {
            $this->stageWeights[$key] = $stage['weight'] ?? 10;
            $this->totalWeight += $this->stageWeights[$key];
        }
    }

    public function startStage(Project $project, ?ProjectScan $scan, string $stage): void
    {
        $overallPercent = $this->calculateOverallProgress($stage, 0);

        $project->updateProgress($stage, $overallPercent);

        if ($scan) {
            $scan->updateProgress($stage, 0);
        }
    }

    public function updateStage(Project $project, ?ProjectScan $scan, string $stage, int $stagePercent): void
    {
        $overallPercent = $this->calculateOverallProgress($stage, $stagePercent);

        $project->updateProgress($stage, $overallPercent);

        if ($scan) {
            $scan->updateProgress($stage, $stagePercent);
        }
    }

    public function completeStage(Project $project, ?ProjectScan $scan, string $stage): void
    {
        $overallPercent = $this->calculateOverallProgress($stage, 100);

        $project->updateProgress($stage, $overallPercent);

        if ($scan) {
            $scan->updateProgress($stage, 100);
        }
    }

    public function complete(Project $project, ?ProjectScan $scan, string $commitSha): void
    {
        $project->markReady($commitSha);

        if ($scan) {
            $scan->markCompleted();
        }
    }

    public function fail(Project $project, ?ProjectScan $scan, string $error): void
    {
        $project->markFailed($error);

        if ($scan) {
            $scan->markFailed($error);
        }
    }

    private function calculateOverallProgress(string $currentStage, int $stagePercent): int
    {
        $completedWeight = 0;
        $stageKeys = array_keys($this->stages);

        foreach ($stageKeys as $key) {
            if ($key === $currentStage) {
                // Add partial progress for current stage
                $completedWeight += ($this->stageWeights[$key] * $stagePercent / 100);
                break;
            }
            // Add full weight for completed stages
            $completedWeight += $this->stageWeights[$key];
        }

        return (int) round(($completedWeight / $this->totalWeight) * 100);
    }

    public function getStageName(string $stage): string
    {
        return $this->stages[$stage]['name'] ?? ucfirst($stage);
    }

    public function getStages(): array
    {
        return array_map(fn($key, $stage) => [
            'id' => $key,
            'name' => $stage['name'],
            'weight' => $stage['weight'],
        ], array_keys($this->stages), $this->stages);
    }

    public function getStatusData(Project $project): array
    {
        $stagesList = $this->getStages();
        $currentStageIndex = null;
        $completedStages = [];

        if ($project->current_stage) {
            foreach ($stagesList as $index => $stage) {
                if ($stage['id'] === $project->current_stage) {
                    $currentStageIndex = $index;
                    break;
                }
                $completedStages[] = $stage['id'];
            }
        } elseif ($project->isReady()) {
            $completedStages = array_column($stagesList, 'id');
        }

        return [
            'status' => $project->status,
            'current_stage' => $project->current_stage,
            'percent' => $project->stage_percent,
            'steps' => array_map(fn($stage) => [
                'id' => $stage['id'],
                'label' => $stage['name'],
                'completed' => in_array($stage['id'], $completedStages),
                'current' => $stage['id'] === $project->current_stage,
            ], $stagesList),
            'error' => $project->last_error,
            'scanned_at' => $project->scanned_at?->toIso8601String(),
            'last_commit_sha' => $project->last_commit_sha,
        ];
    }
}
