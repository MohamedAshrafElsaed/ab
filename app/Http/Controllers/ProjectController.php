<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Jobs\ScanProjectPipelineJob;
use App\Models\Project;
use App\Services\GitHubService;
use App\Services\Projects\ProgressReporter;
use App\Services\Projects\ScannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private GitHubService    $github,
        private ProgressReporter $progressReporter,
    )
    {
    }

    public function confirm(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasGitHubToken()) {
            return redirect()->route('github.connect');
        }

        $validated = $request->validate([
            'repo_full_name' => ['required', 'string'],
        ]);

        $repoFullName = $validated['repo_full_name'];

        $existingProject = $user->projects()->where('repo_full_name', $repoFullName)->first();
        if ($existingProject) {
            return redirect()->route('dashboard')
                ->with('error', 'This repository has already been added.');
        }

        $repoDetails = $this->github->getRepository($user, $repoFullName);

        if (!$repoDetails) {
            return redirect()->route('projects.create')
                ->with('error', 'Unable to fetch repository details. Please try again.');
        }

        return Inertia::render('projects/ConfirmRepository', [
            'repository' => $repoDetails,
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $project = $user->projects()->create([
            'provider' => 'github',
            'repo_full_name' => $validated['repo_full_name'],
            'repo_id' => $validated['repo_id'] ?? null,
            'default_branch' => $validated['default_branch'],
            'status' => 'scanning',
            'current_stage' => 'workspace',
            'stage_percent' => 0,
        ]);

        // Dispatch the scanning pipeline job
        ScanProjectPipelineJob::dispatch($project->id, 'manual');

        return redirect()->route('dashboard')
            ->with('new_project_id', $project->id);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasGitHubToken()) {
            return redirect()->route('github.connect');
        }

        return Inertia::render('projects/SelectRepository', [
            'repositories' => fn() => $this->github->getRepositories($user),
        ]);
    }

    public function show(Project $project): Response|RedirectResponse
    {
        $user = request()->user();

        // Ensure user owns this project
        if ($project->user_id !== $user->id) {
            abort(403);
        }

        // If project is still scanning, redirect to dashboard
        if ($project->isScanning()) {
            return redirect()->route('dashboard');
        }

        $scanner = new ScannerService();

        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'repo_full_name' => $project->repo_full_name,
                'owner' => $project->owner,
                'repo_name' => $project->repo_name,
                'default_branch' => $project->default_branch,
                'status' => $project->status,
                'scanned_at' => $project->scanned_at?->toIso8601String(),
                'last_commit_sha' => $project->last_commit_sha,
                'github_url' => $project->getGitHubUrl(),
                'total_files' => $project->total_files,
                'total_lines' => $project->total_lines,
                'total_size_bytes' => $project->total_size_bytes,
                'stack_info' => $project->stack_info,
                'last_error' => $project->last_error,
            ],
            'directories' => fn() => $scanner->getDirectorySummary($project),
            'topLevelDirectories' => fn() => $scanner->getTopLevelDirectorySummary($project),
            'extensionStats' => fn() => $this->getExtensionStats($project),
        ]);
    }

    private function getExtensionStats(Project $project): array
    {
        return $project->files()
            ->selectRaw('extension, COUNT(*) as count, SUM(size_bytes) as total_size, SUM(line_count) as total_lines')
            ->whereNotNull('extension')
            ->groupBy('extension')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn($row) => [
                'extension' => $row->extension,
                'count' => $row->count,
                'total_size' => $row->total_size,
                'total_lines' => $row->total_lines,
            ])
            ->toArray();
    }

    public function askAI(Project $project): Response|RedirectResponse
    {
        $user = request()->user();

        // Ensure user owns this project
        if ($project->user_id !== $user->id) {
            abort(403);
        }

        // If project is not ready, redirect to show page
        if ($project->status !== 'ready') {
            return redirect()->route('projects.show', $project);
        }

        return Inertia::render('projects/AskAI', [
            'project' => [
                'id' => $project->id,
                'repo_full_name' => $project->repo_full_name,
                'owner' => $project->owner,
                'repo_name' => $project->repo_name,
                'default_branch' => $project->default_branch,
                'status' => $project->status,
                'scanned_at' => $project->scanned_at?->toIso8601String(),
                'github_url' => $project->getGitHubUrl(),
                'total_files' => $project->total_files,
                'total_lines' => $project->total_lines,
                'stack_info' => $project->stack_info,
            ],
        ]);
    }

    public function scanStatus(Project $project): JsonResponse
    {
        $user = request()->user();

        // Ensure user owns this project
        if ($project->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $status = $this->progressReporter->getStatusData($project);

        return response()->json($status);
    }

    public function retryScan(Project $project): RedirectResponse
    {
        $user = request()->user();

        // Ensure user owns this project
        if ($project->user_id !== $user->id) {
            abort(403);
        }

        // Reset project status
        $project->update([
            'status' => 'scanning',
            'current_stage' => 'workspace',
            'stage_percent' => 0,
            'last_error' => null,
        ]);

        // Dispatch new scanning job
        ScanProjectPipelineJob::dispatch($project->id, 'retry');

        return redirect()->route('dashboard')
            ->with('message', 'Scan retry started.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $user = request()->user();

        // Ensure user owns this project
        if ($project->user_id !== $user->id) {
            abort(403);
        }

        // Clean up storage
        $project->cleanupStorage();

        // Delete project (cascades to files, chunks, scans)
        $project->delete();

        return redirect()->route('dashboard')
            ->with('message', 'Project deleted successfully.');
    }
}
