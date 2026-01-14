<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AskProjectRequest;
use App\Models\Project;
use App\Services\AskAI\AskAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class ProjectAskController extends Controller
{
    public function __construct(
        private readonly AskAIService $askAIService,
    )
    {
    }

    public function ask(AskProjectRequest $request, Project $project): JsonResponse
    {
        // Rate limiting
        if (config('askai.rate_limit.enabled', true)) {
            $key = "askai:{$request->user()->id}";
            $maxAttempts = config('askai.rate_limit.max_requests_per_minute', 10);

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'error' => 'Too many requests',
                    'message' => "Please wait {$seconds} seconds before asking another question.",
                    'retry_after' => $seconds,
                ], 429);
            }

            RateLimiter::hit($key, 60);
        }

        // Ensure project is ready
        if ($project->status !== 'ready') {
            return response()->json([
                'error' => 'Project not ready',
                'message' => 'This project is still being scanned. Please wait for the scan to complete.',
            ], 400);
        }

        // Ensure project has knowledge base
        if (!$project->last_kb_scan_id) {
            return response()->json([
                'error' => 'No knowledge base',
                'message' => 'This project has not been scanned yet. Please run a scan first.',
            ], 400);
        }

        // Ask the question
        $response = $this->askAIService->ask(
            $project,
            $request->getQuestion(),
            $request->getDepth()
        );

        return response()->json($response->toArray());
    }

    /**
     * Get project context for the Ask AI feature.
     */
    public function context(Project $project): JsonResponse
    {
        $user = request()->user();

        if ($project->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($project->status !== 'ready') {
            return response()->json([
                'ready' => false,
                'status' => $project->status,
                'message' => 'Project scan not complete.',
            ]);
        }

        $stack = $project->stack_info ?? [];
        $filesCount = $project->files()->count();
        $chunksCount = $project->chunks()->count();

        // Get sample file paths for UI hints
        $samplePaths = $project->files()
            ->where('is_excluded', false)
            ->inRandomOrder()
            ->limit(10)
            ->pluck('path')
            ->toArray();

        // Get declared symbols for autocomplete hints
        $symbols = $project->chunks()
            ->whereNotNull('symbols_declared')
            ->limit(100)
            ->get()
            ->pluck('symbols_declared')
            ->flatten()
            ->unique()
            ->values()
            ->take(50)
            ->toArray();

        return response()->json([
            'ready' => true,
            'project' => [
                'id' => $project->id,
                'repo_full_name' => $project->repo_full_name,
                'scanned_at' => $project->scanned_at?->toIso8601String(),
            ],
            'stats' => [
                'files_count' => $filesCount,
                'chunks_count' => $chunksCount,
                'total_lines' => $project->total_lines,
            ],
            'stack' => [
                'framework' => $stack['framework'] ?? null,
                'frontend' => $stack['frontend'] ?? [],
                'features' => $stack['features'] ?? [],
            ],
            'hints' => [
                'sample_paths' => $samplePaths,
                'symbols' => $symbols,
            ],
            'example_questions' => $this->getExampleQuestions($stack),
        ]);
    }

    /**
     * Generate example questions based on project stack.
     */
    private function getExampleQuestions(array $stack): array
    {
        $questions = [
            'How is authentication implemented in this project?',
            'What routes are defined and which controllers handle them?',
            'Explain the database schema and model relationships.',
        ];

        $framework = $stack['framework'] ?? null;
        $frontend = $stack['frontend'] ?? [];

        if ($framework === 'laravel') {
            $questions[] = 'How are middleware configured in this Laravel app?';
            $questions[] = 'What service providers are registered?';
        }

        if (in_array('vue', $frontend) || in_array('inertia', $frontend)) {
            $questions[] = 'How do Vue components communicate with the backend?';
            $questions[] = 'What shared data is passed to all Inertia pages?';
        }

        if (in_array('livewire', $frontend)) {
            $questions[] = 'What Livewire components exist and what do they do?';
        }

        return array_slice($questions, 0, 5);
    }
}
