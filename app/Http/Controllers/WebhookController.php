<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateProjectFromWebhookJob;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function github(Request $request): JsonResponse
    {
        // Verify signature
        if (!$this->verifyGitHubSignature($request)) {
            Log::warning('GitHub webhook: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->header('X-GitHub-Event');
        $payload = $request->all();

        Log::info('GitHub webhook received', [
            'event' => $event,
            'repository' => $payload['repository']['full_name'] ?? 'unknown',
        ]);

        // Only handle push events
        if ($event !== 'push') {
            return response()->json(['message' => 'Event ignored']);
        }

        // Find the project
        $repoFullName = $payload['repository']['full_name'] ?? null;

        if (!$repoFullName) {
            return response()->json(['error' => 'Repository name not found'], 400);
        }

        // Check if this is the default branch
        $ref = $payload['ref'] ?? '';
        $branch = str_replace('refs/heads/', '', $ref);

        $project = Project::where('repo_full_name', $repoFullName)
            ->where('default_branch', $branch)
            ->first();

        if (!$project) {
            Log::info('GitHub webhook: No matching project found', [
                'repo' => $repoFullName,
                'branch' => $branch,
            ]);
            return response()->json(['message' => 'No matching project']);
        }

        // Dispatch the update job
        UpdateProjectFromWebhookJob::dispatch($project->id, $payload);

        Log::info('GitHub webhook: Update job dispatched', [
            'project_id' => $project->id,
        ]);

        return response()->json(['message' => 'Update queued']);
    }

    private function verifyGitHubSignature(Request $request): bool
    {
        $secret = config('projects.github_webhook_secret');

        // If no secret configured, skip verification (not recommended for production)
        if (empty($secret)) {
            Log::warning('GitHub webhook: No secret configured, skipping verification');
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
