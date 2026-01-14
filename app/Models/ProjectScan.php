<?php

namespace App\Models;

use Database\Factories\ProjectScanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $project_id
 * @property string $status
 * @property string|null $scanner_version
 * @property bool $is_incremental
 * @property string|null $previous_commit_sha
 * @property int $files_scanned
 * @property int $files_excluded
 * @property int $chunks_created
 * @property int $total_lines
 * @property int $total_bytes
 * @property int|null $duration_ms
 * @property string|null $current_stage
 * @property int $stage_percent
 * @property string|null $commit_sha
 * @property string $trigger
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property string|null $last_error
 * @property array<array-key, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $duration_formatted
 * @property-read float|null $duration_seconds
 * @property-read \App\Models\Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan byTrigger(string $trigger)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan completed()
 * @method static \Database\Factories\ProjectScanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan full()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan incremental()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan running()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereChunksCreated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereCommitSha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereCurrentStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereDurationMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereFilesExcluded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereFilesScanned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereIsIncremental($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereMeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan wherePreviousCommitSha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereScannerVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereStagePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereTotalBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereTotalLines($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereTrigger($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectScan whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ProjectScan extends Model
{
    /** @use HasFactory<ProjectScanFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'status',
        'current_stage',
        'stage_percent',
        'commit_sha',
        'previous_commit_sha',
        'trigger',
        'scanner_version',
        'is_incremental',
        'files_scanned',
        'files_excluded',
        'chunks_created',
        'total_lines',
        'total_bytes',
        'duration_ms',
        'started_at',
        'finished_at',
        'last_error',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'project_id' => 'string',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'is_incremental' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // -------------------------------------------------------------------------
    // Status Checks
    // -------------------------------------------------------------------------

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    // -------------------------------------------------------------------------
    // Status Updates
    // -------------------------------------------------------------------------

    public function markStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'finished_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'finished_at' => now(),
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
    // Stats
    // -------------------------------------------------------------------------

    public function updateStats(array $stats): void
    {
        $this->update([
            'files_scanned' => $stats['files_scanned'] ?? $this->files_scanned,
            'files_excluded' => $stats['files_excluded'] ?? $this->files_excluded,
            'chunks_created' => $stats['chunks_created'] ?? $this->chunks_created,
            'total_lines' => $stats['total_lines'] ?? $this->total_lines,
            'total_bytes' => $stats['total_bytes'] ?? $this->total_bytes,
            'duration_ms' => $stats['duration_ms'] ?? $this->duration_ms,
        ]);
    }

    public function getDurationSecondsAttribute(): ?float
    {
        return $this->duration_ms ? $this->duration_ms / 1000 : null;
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->duration_ms) {
            return null;
        }

        $seconds = $this->duration_ms / 1000;

        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds % 60);

        return "{$minutes}m {$remainingSeconds}s";
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeIncremental($query)
    {
        return $query->where('is_incremental', true);
    }

    public function scopeFull($query)
    {
        return $query->where('is_incremental', false);
    }

    public function scopeByTrigger($query, string $trigger)
    {
        return $query->where('trigger', $trigger);
    }
}
