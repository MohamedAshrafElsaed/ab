<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StorageQuotaService
{
    /**
     * Default quota per user in bytes (5GB).
     */
    protected int $defaultQuota;

    /**
     * Maximum file size allowed in bytes (100MB).
     */
    protected int $maxFileSize;

    /**
     * Maximum number of projects per user.
     */
    protected int $maxProjects;

    public function __construct()
    {
        $this->defaultQuota = config('projects.storage_quota', 5 * 1024 * 1024 * 1024);
        $this->maxFileSize = config('projects.max_file_size', 100 * 1024 * 1024);
        $this->maxProjects = config('projects.max_projects_per_user', 50);
    }

    /**
     * Get the storage usage for a user.
     *
     * @return array{used: int, quota: int, percentage: float, projects_count: int, max_projects: int}
     */
    public function getUsageForUser(User $user): array
    {
        $usage = $user->projects()->sum('total_size_bytes');
        $projectsCount = $user->projects()->count();
        $quota = $this->getQuotaForUser($user);

        return [
            'used' => (int) $usage,
            'quota' => $quota,
            'percentage' => $quota > 0 ? round(($usage / $quota) * 100, 2) : 0,
            'projects_count' => $projectsCount,
            'max_projects' => $this->maxProjects,
        ];
    }

    /**
     * Get the quota for a specific user.
     */
    public function getQuotaForUser(User $user): int
    {
        // Could be extended to support custom quotas per user
        return $this->defaultQuota;
    }

    /**
     * Check if a user can create a new project.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canCreateProject(User $user): array
    {
        $projectsCount = $user->projects()->count();

        if ($projectsCount >= $this->maxProjects) {
            return [
                'allowed' => false,
                'reason' => "Maximum number of projects ({$this->maxProjects}) reached.",
            ];
        }

        $usage = $this->getUsageForUser($user);

        if ($usage['percentage'] >= 95) {
            return [
                'allowed' => false,
                'reason' => 'Storage quota nearly exceeded. Please delete some projects first.',
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * Check if a user has enough storage for additional bytes.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function hasStorageFor(User $user, int $additionalBytes): array
    {
        $usage = $this->getUsageForUser($user);
        $newTotal = $usage['used'] + $additionalBytes;

        if ($newTotal > $usage['quota']) {
            $required = $this->formatBytes($additionalBytes);
            $available = $this->formatBytes($usage['quota'] - $usage['used']);

            return [
                'allowed' => false,
                'reason' => "Not enough storage. Required: {$required}, Available: {$available}",
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * Check if a file size is within limits.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function isFileSizeAllowed(int $sizeBytes): array
    {
        if ($sizeBytes > $this->maxFileSize) {
            $max = $this->formatBytes($this->maxFileSize);
            $actual = $this->formatBytes($sizeBytes);

            return [
                'allowed' => false,
                'reason' => "File size ({$actual}) exceeds maximum allowed ({$max}).",
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * Get storage statistics for a project.
     *
     * @return array{total_files: int, total_chunks: int, total_size: int, size_formatted: string}
     */
    public function getProjectStats(Project $project): array
    {
        return [
            'total_files' => $project->total_files,
            'total_chunks' => $project->chunks()->count(),
            'total_size' => $project->total_size_bytes,
            'size_formatted' => $this->formatBytes($project->total_size_bytes),
        ];
    }

    /**
     * Cleanup old scans for all projects.
     */
    public function cleanupOldScans(int $keepPerProject = 3): int
    {
        $totalDeleted = 0;

        Project::query()
            ->where('status', 'ready')
            ->chunk(100, function ($projects) use ($keepPerProject, &$totalDeleted) {
                foreach ($projects as $project) {
                    $scans = $project->listKbScans();

                    if (count($scans) > $keepPerProject) {
                        $project->cleanupOldKbScans($keepPerProject);
                        $totalDeleted += count($scans) - $keepPerProject;
                    }
                }
            });

        return $totalDeleted;
    }

    /**
     * Get users approaching their storage quota.
     *
     * @return \Illuminate\Support\Collection<int, array{user_id: int, email: string, used: int, quota: int, percentage: float}>
     */
    public function getUsersNearQuota(float $thresholdPercentage = 80): \Illuminate\Support\Collection
    {
        return User::query()
            ->select('users.id', 'users.email')
            ->selectRaw('COALESCE(SUM(projects.total_size_bytes), 0) as total_usage')
            ->leftJoin('projects', 'users.id', '=', 'projects.user_id')
            ->groupBy('users.id', 'users.email')
            ->havingRaw('COALESCE(SUM(projects.total_size_bytes), 0) > ?', [$this->defaultQuota * ($thresholdPercentage / 100)])
            ->get()
            ->map(function ($user) {
                return [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'used' => (int) $user->total_usage,
                    'quota' => $this->defaultQuota,
                    'percentage' => round(($user->total_usage / $this->defaultQuota) * 100, 2),
                ];
            });
    }

    /**
     * Format bytes to human readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
