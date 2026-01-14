<?php

namespace App\Http\Controllers;

use App\Services\Projects\ProgressReporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private ProgressReporter $progressReporter,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $projects = $user->projects()
            ->latest()
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'repo_full_name' => $project->repo_full_name,
                'name' => $project->repo_name,
                'owner' => $project->owner,
                'default_branch' => $project->default_branch,
                'status' => $project->status,
                'current_stage' => $project->current_stage,
                'stage_percent' => $project->stage_percent,
                'scanned_at' => $project->scanned_at?->toIso8601String(),
                'last_commit_sha' => $project->last_commit_sha,
                'last_error' => $project->last_error,
                'total_files' => $project->total_files,
                'total_lines' => $project->total_lines,
                'stack_info' => $project->stack_info,
                'created_at' => $project->created_at->toIso8601String(),
                'updated_at' => $project->updated_at->toIso8601String(),
            ]);

        $newProjectId = $request->session()->get('new_project_id');
        $request->session()->forget('new_project_id');

        return Inertia::render('Dashboard', [
            'projects' => $projects,
            'newProjectId' => $newProjectId,
            'hasGitHubToken' => $user->hasGitHubToken(),
            'pipelineStages' => $this->progressReporter->getStages(),
        ]);
    }
}
