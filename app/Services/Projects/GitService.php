<?php

namespace App\Services\Projects;

use App\Models\Project;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GitService
{
    public function ensureWorkspace(Project $project): void
    {
        $paths = [
            $project->storage_path,
            $project->repo_path,
            $project->knowledge_path,
            $project->chunks_path,
            $project->indexes_path,
            $project->kb_base_path,
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function cloneOrUpdate(Project $project, string $token): string
    {
        $this->ensureWorkspace($project);

        if ($project->hasLocalRepo()) {
            return $this->fetchAndCheckout($project, $token);
        }

        return $this->cloneRepository($project, $token);
    }

    /**
     * @throws Exception
     */
    private function cloneRepository(Project $project, string $token): string
    {
        $cloneUrl = $this->getAuthenticatedUrl($project, $token);
        $repoPath = $project->repo_path;
        $branch = $project->default_branch;

        $result = Process::timeout(300)
            ->path(dirname($repoPath))
            ->run([
                'git', 'clone',
                '--depth=1',
                '--branch', $branch,
                '--single-branch',
                $cloneUrl,
                basename($repoPath),
            ]);

        if (!$result->successful()) {
            $error = $this->sanitizeGitOutput($result->errorOutput(), $token);
            Log::error('Git clone failed', ['error' => $error, 'project_id' => $project->id]);
            throw new Exception('Failed to clone repository: ' . $error);
        }

        return $this->getCurrentCommitSha($project);
    }

    /**
     * @throws Exception
     */
    private function fetchAndCheckout(Project $project, string $token): string
    {
        $repoPath = $project->repo_path;
        $branch = $project->default_branch;

        $this->updateRemoteUrl($project, $token);

        $result = Process::timeout(120)
            ->path($repoPath)
            ->run(['git', 'fetch', 'origin', $branch]);

        if (!$result->successful()) {
            $error = $this->sanitizeGitOutput($result->errorOutput(), $token);
            throw new Exception('Failed to fetch repository: ' . $error);
        }

        $result = Process::timeout(60)
            ->path($repoPath)
            ->run(['git', 'reset', '--hard', "origin/{$branch}"]);

        if (!$result->successful()) {
            throw new Exception('Failed to reset repository: ' . $result->errorOutput());
        }

        Process::timeout(60)->path($repoPath)->run(['git', 'clean', '-fd']);

        return $this->getCurrentCommitSha($project);
    }

    private function updateRemoteUrl(Project $project, string $token): void
    {
        $url = $this->getAuthenticatedUrl($project, $token);
        Process::timeout(10)
            ->path($project->repo_path)
            ->run(['git', 'remote', 'set-url', 'origin', $url]);
    }

    public function getCurrentCommitSha(Project $project): string
    {
        $result = Process::timeout(10)
            ->path($project->repo_path)
            ->run(['git', 'rev-parse', 'HEAD']);

        if (!$result->successful()) {
            throw new Exception('Failed to get commit SHA');
        }

        return trim($result->output());
    }

    public function getChangedFiles(Project $project, string $fromSha, string $toSha): array
    {
        $result = Process::timeout(30)
            ->path($project->repo_path)
            ->run(['git', 'diff', '--name-status', $fromSha, $toSha]);

        if (!$result->successful()) {
            return [];
        }

        $changes = [
            'added' => [],
            'modified' => [],
            'deleted' => [],
        ];

        foreach (explode("\n", trim($result->output())) as $line) {
            if (empty($line)) continue;

            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) < 2) continue;

            [$status, $path] = $parts;

            match ($status[0]) {
                'A' => $changes['added'][] = $path,
                'M', 'R' => $changes['modified'][] = $path,
                'D' => $changes['deleted'][] = $path,
                default => null,
            };
        }

        return $changes;
    }

    private function getAuthenticatedUrl(Project $project, string $token): string
    {
        return sprintf(
            'https://%s@github.com/%s.git',
            $token,
            $project->repo_full_name
        );
    }

    private function sanitizeGitOutput(string $output, string $token): string
    {
        return str_replace($token, '[REDACTED]', $output);
    }

    public function validateRepoPath(string $path): bool
    {
        $realPath = realpath($path);
        $storagePath = realpath(config('projects.storage_path'));

        if (!$realPath || !$storagePath) {
            return false;
        }

        return str_starts_with($realPath, $storagePath);
    }
}
