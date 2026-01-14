<?php

namespace App\Models;

use App\DTOs\FileOperation;
use App\DTOs\RiskAssessment;
use App\Enums\ComplexityLevel;
use App\Enums\FileExecutionStatus;
use App\Enums\PlanStatus;
use Database\Factories\ExecutionPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $project_id
 * @property string $conversation_id
 * @property string|null $intent_analysis_id
 * @property PlanStatus $status
 * @property string $title
 * @property string $description
 * @property array<array-key, mixed> $plan_data
 * @property array<array-key, mixed> $file_operations
 * @property ComplexityLevel $estimated_complexity
 * @property int $estimated_files_affected
 * @property array<array-key, mixed>|null $risks
 * @property array<array-key, mixed>|null $prerequisites
 * @property string|null $user_feedback
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $execution_started_at
 * @property Carbon|null $execution_completed_at
 * @property array<array-key, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\User|null $approver
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FileExecution> $fileExecutions
 * @property-read int|null $file_executions_count
 * @property-read bool $can_execute
 * @property-read int|null $execution_duration
 * @property-read \Illuminate\Support\Collection<int, \App\DTOs\FileOperation> $file_operations_dtos
 * @property-read bool $is_modifiable
 * @property-read RiskAssessment $risk_assessment
 * @property-read int $total_files
 * @property-read \App\Models\IntentAnalysis|null $intentAnalysis
 * @property-read \App\Models\Project $project
 * @method static Builder<static>|ExecutionPlan active()
 * @method static \Database\Factories\ExecutionPlanFactory factory($count = null, $state = [])
 * @method static Builder<static>|ExecutionPlan forConversation(string $conversationId)
 * @method static Builder<static>|ExecutionPlan forProject(string $projectId)
 * @method static Builder<static>|ExecutionPlan newModelQuery()
 * @method static Builder<static>|ExecutionPlan newQuery()
 * @method static Builder<static>|ExecutionPlan pendingReview()
 * @method static Builder<static>|ExecutionPlan query()
 * @method static Builder<static>|ExecutionPlan whereApprovedAt($value)
 * @method static Builder<static>|ExecutionPlan whereApprovedBy($value)
 * @method static Builder<static>|ExecutionPlan whereConversationId($value)
 * @method static Builder<static>|ExecutionPlan whereCreatedAt($value)
 * @method static Builder<static>|ExecutionPlan whereDescription($value)
 * @method static Builder<static>|ExecutionPlan whereEstimatedComplexity($value)
 * @method static Builder<static>|ExecutionPlan whereEstimatedFilesAffected($value)
 * @method static Builder<static>|ExecutionPlan whereExecutionCompletedAt($value)
 * @method static Builder<static>|ExecutionPlan whereExecutionStartedAt($value)
 * @method static Builder<static>|ExecutionPlan whereFileOperations($value)
 * @method static Builder<static>|ExecutionPlan whereId($value)
 * @method static Builder<static>|ExecutionPlan whereIntentAnalysisId($value)
 * @method static Builder<static>|ExecutionPlan whereMetadata($value)
 * @method static Builder<static>|ExecutionPlan wherePlanData($value)
 * @method static Builder<static>|ExecutionPlan wherePrerequisites($value)
 * @method static Builder<static>|ExecutionPlan whereProjectId($value)
 * @method static Builder<static>|ExecutionPlan whereRisks($value)
 * @method static Builder<static>|ExecutionPlan whereStatus($value)
 * @method static Builder<static>|ExecutionPlan whereTitle($value)
 * @method static Builder<static>|ExecutionPlan whereUpdatedAt($value)
 * @method static Builder<static>|ExecutionPlan whereUserFeedback($value)
 * @method static Builder<static>|ExecutionPlan withStatus(\App\Enums\PlanStatus $status)
 * @mixin \Eloquent
 */
class ExecutionPlan extends Model
{
    /** @use HasFactory<ExecutionPlanFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $table = 'execution_plans';
    protected $keyType = 'string';
    protected $fillable = [
        'project_id',
        'conversation_id',
        'intent_analysis_id',
        'status',
        'title',
        'description',
        'plan_data',
        'file_operations',
        'estimated_complexity',
        'estimated_files_affected',
        'risks',
        'prerequisites',
        'user_feedback',
        'approved_at',
        'approved_by',
        'execution_started_at',
        'execution_completed_at',
        'metadata',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function intentAnalysis(): BelongsTo
    {
        return $this->belongsTo(IntentAnalysis::class, 'intent_analysis_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @param Builder<ExecutionPlan> $query
     * @return Builder<ExecutionPlan>
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', PlanStatus::PendingReview);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * @param Builder<ExecutionPlan> $query
     * @return Builder<ExecutionPlan>
     */
    public function scopeForProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * @param Builder<ExecutionPlan> $query
     * @return Builder<ExecutionPlan>
     */
    public function scopeForConversation(Builder $query, string $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * @param Builder<ExecutionPlan> $query
     * @return Builder<ExecutionPlan>
     */
    public function scopeWithStatus(Builder $query, PlanStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param Builder<ExecutionPlan> $query
     * @return Builder<ExecutionPlan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            PlanStatus::Completed->value,
            PlanStatus::Failed->value,
            PlanStatus::Rejected->value,
        ]);
    }

    /**
     * Get file operations as DTOs.
     *
     * @return Collection<int, FileOperation>
     */
    public function getFileOperationsDtosAttribute(): Collection
    {
        $operations = $this->file_operations ?? [];

        return collect($operations)->map(fn($op) => FileOperation::fromArray($op));
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Get total files affected.
     */
    public function getTotalFilesAttribute(): int
    {
        return count($this->file_operations ?? []);
    }

    /**
     * Get risk assessment DTO.
     */
    public function getRiskAssessmentAttribute(): RiskAssessment
    {
        return RiskAssessment::calculate(
            $this->risks ?? [],
            $this->prerequisites ?? [],
            $this->plan_data['manual_steps'] ?? []
        );
    }

    /**
     * Get execution duration in seconds.
     */
    public function getExecutionDurationAttribute(): ?int
    {
        if (!$this->execution_started_at || !$this->execution_completed_at) {
            return null;
        }

        return $this->execution_completed_at->diffInSeconds($this->execution_started_at);
    }

    /**
     * Check if plan is modifiable.
     */
    public function getIsModifiableAttribute(): bool
    {
        return $this->status->isModifiable();
    }

    /**
     * Check if plan can be executed.
     */
    public function getCanExecuteAttribute(): bool
    {
        return $this->status->canExecute();
    }

    /**
     * Approve the plan for execution.
     *
     * @throws InvalidArgumentException
     */
    public function approve(?int $userId = null): void
    {
        $this->transitionTo(PlanStatus::Approved);
        $this->update([
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);
    }

    // =========================================================================
    // Status Transition Methods
    // =========================================================================

    /**
     * Transition to a new status with validation.
     *
     * @throws InvalidArgumentException
     */
    private function transitionTo(PlanStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition from '{$this->status->value}' to '{$newStatus->value}'"
            );
        }

        $this->update(['status' => $newStatus]);
    }

    /**
     * Reject the plan.
     *
     * @throws InvalidArgumentException
     */
    public function reject(string $reason): void
    {
        $this->transitionTo(PlanStatus::Rejected);
        $this->update([
            'user_feedback' => $reason,
            'metadata' => array_merge($this->metadata ?? [], [
                'rejected_at' => now()->toIso8601String(),
                'rejection_reason' => $reason,
            ]),
        ]);
    }

    /**
     * Mark plan as executing.
     *
     * @throws InvalidArgumentException
     */
    public function markExecuting(): void
    {
        $this->transitionTo(PlanStatus::Executing);
        $this->update([
            'execution_started_at' => now(),
        ]);
    }

    /**
     * Mark plan as completed.
     *
     * @throws InvalidArgumentException
     */
    public function markCompleted(): void
    {
        $this->transitionTo(PlanStatus::Completed);
        $this->update([
            'execution_completed_at' => now(),
        ]);
    }

    /**
     * Mark plan as failed.
     *
     * @throws InvalidArgumentException
     */
    public function markFailed(string $error): void
    {
        $this->transitionTo(PlanStatus::Failed);
        $this->update([
            'execution_completed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'failure_reason' => $error,
                'failed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Submit for review.
     *
     * @throws InvalidArgumentException
     */
    public function submitForReview(): void
    {
        $this->transitionTo(PlanStatus::PendingReview);
    }

    /**
     * Revert to draft for editing.
     *
     * @throws InvalidArgumentException
     */
    public function revertToDraft(): void
    {
        $this->transitionTo(PlanStatus::Draft);
    }

    /**
     * Add user feedback for plan refinement.
     */
    public function addUserFeedback(string $feedback): void
    {
        $existingFeedback = $this->user_feedback ?? '';
        $separator = $existingFeedback ? "\n---\n" : '';

        $this->update([
            'user_feedback' => $existingFeedback . $separator . $feedback,
        ]);
    }

    // =========================================================================
    // Other Methods
    // =========================================================================

    /**
     * Get files in execution order (sorted by priority).
     *
     * @return array<array<string, mixed>>
     */
    public function getOrderedFileOperations(): array
    {
        $operations = $this->file_operations ?? [];

        usort($operations, fn($a, $b) => ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999));

        return $operations;
    }

    /**
     * Check if a specific file is affected.
     */
    public function affectsFile(string $path): bool
    {
        foreach ($this->file_operations ?? [] as $op) {
            if ($op['path'] === $path || ($op['new_path'] ?? null) === $path) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a short summary for display.
     */
    public function getSummary(): string
    {
        $ops = $this->getFilesByOperationType();
        $parts = [];

        foreach ($ops as $type => $files) {
            $count = count($files);
            $parts[] = "{$count} " . ($count === 1 ? $type : "{$type}s");
        }

        return implode(', ', $parts) ?: 'No file operations';
    }

    /**
     * Get files grouped by operation type.
     *
     * @return array<string, array<string>>
     */
    public function getFilesByOperationType(): array
    {
        $grouped = [];

        foreach ($this->file_operations ?? [] as $op) {
            $type = $op['type'] ?? 'unknown';
            $grouped[$type][] = $op['path'];
        }

        return $grouped;
    }

    /**
     * Get the next pending file execution.
     */
    public function getNextPendingExecution(): ?FileExecution
    {
        return $this->fileExecutions()
            ->where('status', FileExecutionStatus::Pending)
            ->orderBy('file_operation_index')
            ->first();
    }

    /**
     * Get all file executions for this plan.
     *
     * @return HasMany<FileExecution>
     */
    public function fileExecutions(): HasMany
    {
        return $this->hasMany(FileExecution::class)->orderBy('file_operation_index');
    }

    /**
     * Check if all executions are complete.
     */
    public function allExecutionsComplete(): bool
    {
        $progress = $this->getExecutionProgress();
        return $progress['pending'] === 0 && $progress['total'] > 0;
    }

    /**
     * Get execution progress.
     *
     * @return array{total: int, completed: int, failed: int, pending: int, percentage: float}
     */
    public function getExecutionProgress(): array
    {
        $executions = $this->fileExecutions;
        $total = $executions->count();

        if ($total === 0) {
            $total = count($this->file_operations ?? []);
            return [
                'total' => $total,
                'completed' => 0,
                'failed' => 0,
                'pending' => $total,
                'percentage' => 0.0,
            ];
        }

        $completed = $executions->where('status', FileExecutionStatus::Completed)->count();
        $failed = $executions->where('status', FileExecutionStatus::Failed)->count();
        $pending = $executions->where('status', FileExecutionStatus::Pending)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'pending' => $pending,
            'percentage' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Get executions that can be rolled back.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, FileExecution>
     */
    public function getRollbackableExecutions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->fileExecutions()
            ->where('status', FileExecutionStatus::Completed)
            ->whereNotNull('original_content')
            ->orderByDesc('file_operation_index')
            ->get();
    }

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'plan_data' => 'array',
            'file_operations' => 'array',
            'estimated_complexity' => ComplexityLevel::class,
            'estimated_files_affected' => 'integer',
            'risks' => 'array',
            'prerequisites' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'execution_started_at' => 'datetime',
            'execution_completed_at' => 'datetime',
        ];
    }
}
