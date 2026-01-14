<?php

namespace App\Models;

use App\Enums\FileExecutionStatus;
use App\Enums\FileOperationType;
use Database\Factories\FileExecutionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileExecution extends Model
{
    /** @use HasFactory<FileExecutionFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'execution_plan_id',
        'file_operation_index',
        'operation_type',
        'file_path',
        'new_file_path',
        'status',
        'original_content',
        'new_content',
        'diff',
        'error_message',
        'user_approved',
        'auto_approved',
        'backup_path',
        'execution_started_at',
        'execution_completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => FileExecutionStatus::class,
            'operation_type' => FileOperationType::class,
            'user_approved' => 'boolean',
            'auto_approved' => 'boolean',
            'execution_started_at' => 'datetime',
            'execution_completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function executionPlan(): BelongsTo
    {
        return $this->belongsTo(ExecutionPlan::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', FileExecutionStatus::Pending);
    }

    /**
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', FileExecutionStatus::InProgress);
    }

    /**
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', FileExecutionStatus::Completed);
    }

    /**
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', FileExecutionStatus::Failed);
    }

    /**
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopeRolledBack(Builder $query): Builder
    {
        return $query->where('status', FileExecutionStatus::RolledBack);
    }

    /**
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('user_approved', true)->orWhere('auto_approved', true);
        });
    }

    /**
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('status', FileExecutionStatus::Pending)
            ->where('user_approved', false)
            ->where('auto_approved', false);
    }

    /**
     * FIX: Rollbackable scope now includes CREATE operations (which have null original_content)
     *
     * - CREATE operations: can rollback by deleting the file (original_content is null)
     * - MODIFY/DELETE/MOVE/RENAME: can rollback if original_content exists
     *
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopeRollbackable(Builder $query): Builder
    {
        return $query->where('status', FileExecutionStatus::Completed)
            ->where(function ($q) {
                // CREATE operations can be rolled back by deleting the file
                $q->where('operation_type', FileOperationType::Create)
                    // Other operations need original_content to restore
                    ->orWhereNotNull('original_content');
            });
    }

    /**
     * @param Builder<FileExecution> $query
     * @return Builder<FileExecution>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('file_operation_index');
    }

    // =========================================================================
    // Status Transitions
    // =========================================================================

    public function markInProgress(): void
    {
        $this->update([
            'status' => FileExecutionStatus::InProgress,
            'execution_started_at' => now(),
        ]);
    }

    public function markCompleted(string $newContent, ?string $diff = null): void
    {
        $this->update([
            'status' => FileExecutionStatus::Completed,
            'new_content' => $newContent,
            'diff' => $diff,
            'execution_completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => FileExecutionStatus::Failed,
            'error_message' => $error,
            'execution_completed_at' => now(),
        ]);
    }

    public function markSkipped(string $reason): void
    {
        $this->update([
            'status' => FileExecutionStatus::Skipped,
            'metadata' => array_merge($this->metadata ?? [], [
                'skip_reason' => $reason,
                'skipped_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function markRolledBack(): void
    {
        $this->update([
            'status' => FileExecutionStatus::RolledBack,
            'metadata' => array_merge($this->metadata ?? [], [
                'rolled_back_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function approve(): void
    {
        $this->update(['user_approved' => true]);
    }

    public function autoApprove(): void
    {
        $this->update(['auto_approved' => true]);
    }

    public function skip(string $reason = 'User skipped'): void
    {
        $this->markSkipped($reason);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * FIX: can_rollback now properly handles CREATE operations
     *
     * - CREATE operations: can always rollback (delete the created file)
     * - Other operations: need original_content to restore
     */
    public function getCanRollbackAttribute(): bool
    {
        if ($this->status !== FileExecutionStatus::Completed) {
            return false;
        }

        // CREATE operations can be rolled back by deleting the file
        if ($this->operation_type === FileOperationType::Create) {
            return true;
        }

        // Other operations need original_content to restore
        return $this->original_content !== null;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->user_approved || $this->auto_approved;
    }

    public function getIsTerminalAttribute(): bool
    {
        return $this->status->isTerminal();
    }

    public function getDurationSecondsAttribute(): ?float
    {
        if (!$this->execution_started_at || !$this->execution_completed_at) {
            return null;
        }
        return $this->execution_completed_at->diffInMilliseconds($this->execution_started_at) / 1000;
    }

    /**
     * @return array<array{line: string, type: string}>
     */
    public function getDiffLinesAttribute(): array
    {
        if (empty($this->diff)) {
            return [];
        }

        $lines = explode("\n", $this->diff);
        $result = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '+') && !str_starts_with($line, '+++')) {
                $result[] = ['line' => $line, 'type' => 'added'];
            } elseif (str_starts_with($line, '-') && !str_starts_with($line, '---')) {
                $result[] = ['line' => $line, 'type' => 'removed'];
            } elseif (str_starts_with($line, '@@')) {
                $result[] = ['line' => $line, 'type' => 'header'];
            } else {
                $result[] = ['line' => $line, 'type' => 'context'];
            }
        }

        return $result;
    }

    public function getAddedLinesCountAttribute(): int
    {
        return count(array_filter($this->diff_lines, fn($l) => $l['type'] === 'added'));
    }

    public function getRemovedLinesCountAttribute(): int
    {
        return count(array_filter($this->diff_lines, fn($l) => $l['type'] === 'removed'));
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    public function setOriginalContent(string $content): void
    {
        $this->update(['original_content' => $content]);
    }

    public function setBackupPath(string $path): void
    {
        $this->update(['backup_path' => $path]);
    }

    public function addMetadata(array $data): void
    {
        $this->update([
            'metadata' => array_merge($this->metadata ?? [], $data),
        ]);
    }

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'index' => $this->file_operation_index,
            'path' => $this->file_path,
            'operation' => $this->operation_type->value,
            'status' => $this->status->value,
            'approved' => $this->is_approved,
            'can_rollback' => $this->can_rollback,
            'duration_seconds' => $this->duration_seconds,
            'added_lines' => $this->added_lines_count,
            'removed_lines' => $this->removed_lines_count,
        ];
    }
}
