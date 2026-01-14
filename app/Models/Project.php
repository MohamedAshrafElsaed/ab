<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $provider
 * @property string $repo_full_name
 * @property string|null $repo_id
 * @property string $default_branch
 * @property string|null $selected_branch
 * @property string $status
 * @property string|null $current_stage
 * @property int $stage_percent
 * @property Carbon|null $scanned_at
 * @property string|null $last_commit_sha
 * @property string|null $last_kb_scan_id
 * @property string|null $parent_commit_sha
 * @property string|null $scan_output_version
 * @property string|null $exclusion_rules_version
 * @property Carbon|null $last_migration_at
 * @property string|null $last_error
 * @property array<array-key, mixed>|null $stack_info
 * @property int $total_files
 * @property int $total_lines
 * @property int $total_size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ProjectFileChunk> $chunks
 * @property-read int|null $chunks_count
 * @property-read Collection<int, ProjectFile> $files
 * @property-read int|null $files_count
 * @property-read string $active_branch
 * @property-read string $chunks_path
 * @property-read string $indexes_path
 * @property-read string $kb_base_path
 * @property-read string $knowledge_path
 * @property-read string|null $latest_kb_path
 * @property-read string $owner
 * @property-read string $repo_name
 * @property-read string $repo_path
 * @property-read string $storage_path
 * @property-read Collection<int, ProjectScan> $scans
 * @property-read int|null $scans_count
 * @property-read User $user
 * @method static ProjectFactory factory($count = null, $state = [])
 * @method static Builder<static>|Project newModelQuery()
 * @method static Builder<static>|Project newQuery()
 * @method static Builder<static>|Project query()
 * @method static Builder<static>|Project whereCreatedAt($value)
 * @method static Builder<static>|Project whereCurrentStage($value)
 * @method static Builder<static>|Project whereDefaultBranch($value)
 * @method static Builder<static>|Project whereExclusionRulesVersion($value)
 * @method static Builder<static>|Project whereId($value)
 * @method static Builder<static>|Project whereLastCommitSha($value)
 * @method static Builder<static>|Project whereLastError($value)
 * @method static Builder<static>|Project whereLastKbScanId($value)
 * @method static Builder<static>|Project whereLastMigrationAt($value)
 * @method static Builder<static>|Project whereParentCommitSha($value)
 * @method static Builder<static>|Project whereProvider($value)
 * @method static Builder<static>|Project whereRepoFullName($value)
 * @method static Builder<static>|Project whereRepoId($value)
 * @method static Builder<static>|Project whereScanOutputVersion($value)
 * @method static Builder<static>|Project whereScannedAt($value)
 * @method static Builder<static>|Project whereSelectedBranch($value)
 * @method static Builder<static>|Project whereStackInfo($value)
 * @method static Builder<static>|Project whereStagePercent($value)
 * @method static Builder<static>|Project whereStatus($value)
 * @method static Builder<static>|Project whereTotalFiles($value)
 * @method static Builder<static>|Project whereTotalLines($value)
 * @method static Builder<static>|Project whereTotalSizeBytes($value)
 * @method static Builder<static>|Project whereUpdatedAt($value)
 * @method static Builder<static>|Project whereUserId($value)
 * @mixin Eloquent
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'user_id',
        'provider',
        'repo_full_name',
        'repo_id',
        'default_branch',
        'selected_branch',
        'status',
        'current_stage',
        'stage_percent',
        'scanned_at',
        'last_commit_sha',
        'parent_commit_sha',
        'scan_output_version',
        'exclusion_rules_version',
        'last_migration_at',
        'last_error',
        'stack_info',
        'total_files',
        'total_lines',
        'total_size_bytes',
        'last_kb_scan_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function latestScan(): ?ProjectScan
    {
        return $this->scans()->latest()->first();
    }

    public function scans(): HasMany
    {
        return $this->hasMany(ProjectScan::class);
    }

    public function getStoragePathAttribute(): string
    {
        return config('projects.storage_path') . '/' . $this->id;
    }

    public function getRepoPathAttribute(): string
    {
        return $this->storage_path . '/repo';
    }

    public function getKnowledgePathAttribute(): string
    {
        return $this->storage_path . '/knowledge';
    }

    // -------------------------------------------------------------------------
    // Path Accessors - Unified Storage
    // -------------------------------------------------------------------------

    public function getChunksPathAttribute(): string
    {
        return $this->knowledge_path . '/chunks';
    }

    public function getIndexesPathAttribute(): string
    {
        return $this->knowledge_path . '/indexes';
    }

    public function getKbBasePathAttribute(): string
    {
        return $this->knowledge_path . '/scans';
    }

    public function getLatestKbPathAttribute(): ?string
    {
        if (!$this->last_kb_scan_id) {
            return null;
        }
        return $this->getKbScanPath($this->last_kb_scan_id);
    }

    public function getKbScanPath(string $scanId): string
    {
        return $this->kb_base_path . '/' . $scanId;
    }

    public function getKbScanMetaPath(string $scanId): string
    {
        return $this->getKbScanPath($scanId) . '/scan_meta.json';
    }

    public function getKbFilesIndexPath(string $scanId): string
    {
        $basePath = $this->getKbScanPath($scanId);
        if (file_exists($basePath . '/files_index.ndjson')) {
            return $basePath . '/files_index.ndjson';
        }
        return $basePath . '/files_index.json';
    }

    public function getKbChunksPath(string $scanId): string
    {
        return $this->getKbScanPath($scanId) . '/chunks.ndjson';
    }

    public function getKbDirectoryStatsPath(string $scanId): string
    {
        return $this->getKbScanPath($scanId) . '/directory_stats.json';
    }

    public function getOwnerAttribute(): string
    {
        return explode('/', $this->repo_full_name)[0] ?? '';
    }

    public function getRepoNameAttribute(): string
    {
        return explode('/', $this->repo_full_name)[1] ?? $this->repo_full_name;
    }

    public function getActiveBranchAttribute(): string
    {
        return $this->selected_branch ?? $this->default_branch;
    }

    public function isScanning(): bool
    {
        return $this->status === 'scanning';
    }

    // -------------------------------------------------------------------------
    // Repository Accessors
    // -------------------------------------------------------------------------

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // -------------------------------------------------------------------------
    // Status Checks
    // -------------------------------------------------------------------------

    public function needsMigration(): bool
    {
        return $this->scan_output_version === null || version_compare($this->scan_output_version, '2.1.0', '<');
    }

    public function hasLocalRepo(): bool
    {
        return is_dir($this->repo_path . '/.git');
    }

    public function markScanning(): void
    {
        $this->update([
            'status' => 'scanning',
            'last_error' => null,
        ]);
    }

    public function markReady(string $commitSha): void
    {
        $this->update([
            'status' => 'ready',
            'last_commit_sha' => $commitSha,
            'scanned_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $error,
        ]);
    }

    public function updateProgress(string $stage, int $percent): void
    {
        $this->update([
            'current_stage' => $stage,
            'stage_percent' => $percent,
        ]);
    }

    // -------------------------------------------------------------------------
    // Status Updates
    // -------------------------------------------------------------------------

    public function updateStats(int $totalFiles, int $totalLines, int $totalBytes): void
    {
        $this->update([
            'total_files' => $totalFiles,
            'total_lines' => $totalLines,
            'total_size_bytes' => $totalBytes,
        ]);
    }

    public function updateStackInfo(array $stack): void
    {
        $this->update(['stack_info' => $stack]);
    }

    public function getGitCloneUrl(): string
    {
        return 'https://github.com/' . $this->repo_full_name . '.git';
    }

    public function getGitHubFileUrl(string $path, ?int $line = null): string
    {
        $url = $this->getGitHubUrl() . '/blob/' . $this->active_branch . '/' . $path;

        if ($line !== null) {
            $url .= '#L' . $line;
        }

        return $url;
    }

    public function getGitHubUrl(): string
    {
        return 'https://github.com/' . $this->repo_full_name;
    }

    public function getIncludedFiles()
    {
        return $this->files()->where('is_excluded', false);
    }

    // -------------------------------------------------------------------------
    // URL Helpers
    // -------------------------------------------------------------------------

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function getExcludedFiles()
    {
        return $this->files()->where('is_excluded', true);
    }

    public function getFilesByLanguage(string $language)
    {
        return $this->files()->where('language', $language)->where('is_excluded', false);
    }

    // -------------------------------------------------------------------------
    // File/Chunk Queries
    // -------------------------------------------------------------------------

    public function getChunksForFile(string $path)
    {
        return $this->chunks()->where('path', $path)->orderBy('start_line');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ProjectFileChunk::class);
    }

    public function findChunkById(string $chunkId): ?ProjectFileChunk
    {
        return $this->chunks()->where('chunk_id', $chunkId)->first();
    }

    public function cleanupStorage(): void
    {
        $path = $this->storage_path;
        if (is_dir($path)) {
            $this->recursiveDelete($path);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir . '/' . $object;
                    if (is_dir($path)) {
                        $this->recursiveDelete($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }

    // -------------------------------------------------------------------------
    // Cleanup
    // -------------------------------------------------------------------------

    public function cleanupOldKbScans(int $keep = 3): void
    {
        $scans = $this->listKbScans();

        if (count($scans) <= $keep) {
            return;
        }

        $toDelete = array_slice($scans, $keep);
        foreach ($toDelete as $scan) {
            $scanPath = $this->getKbScanPath($scan['scan_id']);
            if (is_dir($scanPath)) {
                $this->recursiveDelete($scanPath);
            }
        }
    }

    public function listKbScans(): array
    {
        $basePath = $this->kb_base_path;
        if (!is_dir($basePath)) {
            return [];
        }

        $scans = [];
        foreach (scandir($basePath) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir($basePath . '/' . $entry) && str_starts_with($entry, 'scan_')) {
                $metaPath = $basePath . '/' . $entry . '/scan_meta.json';
                if (file_exists($metaPath)) {
                    $meta = json_decode(file_get_contents($metaPath), true);
                    $scans[] = [
                        'scan_id' => $entry,
                        'scanned_at' => $meta['scanned_at_iso'] ?? null,
                        'head_commit_sha' => $meta['head_commit_sha'] ?? null,
                        'total_chunks' => $meta['stats']['total_chunks'] ?? 0,
                    ];
                }
            }
        }

        usort($scans, fn($a, $b) => ($b['scanned_at'] ?? '') <=> ($a['scanned_at'] ?? ''));

        return $scans;
    }

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'last_migration_at' => 'datetime',
            'stack_info' => 'array',
        ];
    }
}
