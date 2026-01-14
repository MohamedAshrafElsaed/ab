<?php

namespace App\Models;

use App\Enums\AgentMessageType;
use App\Enums\ConversationPhase;
use Database\Factories\AgentConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $project_id
 * @property int $user_id
 * @property string|null $title
 * @property string $status
 * @property ConversationPhase $current_phase
 * @property string|null $current_intent_id
 * @property string|null $current_plan_id
 * @property array<string, mixed>|null $context_summary
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User $user
 * @property-read IntentAnalysis|null $currentIntent
 * @property-read ExecutionPlan|null $currentPlan
 * @property-read Collection<int, AgentMessage> $messages
 * @property-read int|null $messages_count
 * @property-read bool $is_active
 * @property-read bool $requires_user_action
 */
class AgentConversation extends Model
{
    /** @use HasFactory<AgentConversationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'user_id',
        'title',
        'status',
        'current_phase',
        'current_intent_id',
        'current_plan_id',
        'context_summary',
        'metadata',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentIntent(): BelongsTo
    {
        return $this->belongsTo(IntentAnalysis::class, 'current_intent_id');
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(ExecutionPlan::class, 'current_plan_id');
    }

    /**
     * @param Builder<AgentConversation> $query
     * @return Builder<AgentConversation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param Builder<AgentConversation> $query
     * @return Builder<AgentConversation>
     */
    public function scopeForProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * @param Builder<AgentConversation> $query
     * @return Builder<AgentConversation>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param Builder<AgentConversation> $query
     * @return Builder<AgentConversation>
     */
    public function scopeInPhase(Builder $query, ConversationPhase $phase): Builder
    {
        return $query->where('current_phase', $phase->value);
    }

    /**
     * @param Builder<AgentConversation> $query
     * @return Builder<AgentConversation>
     */
    public function scopeRequiringAction(Builder $query): Builder
    {
        return $query->whereIn('current_phase', [
            ConversationPhase::Clarification->value,
            ConversationPhase::Approval->value,
        ]);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' && !$this->current_phase->isTerminal();
    }

    public function getRequiresUserActionAttribute(): bool
    {
        return $this->current_phase->requiresUserAction();
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function addMessage(
        string           $role,
        string           $content,
        AgentMessageType $type = AgentMessageType::Text,
        array            $attachments = [],
        array            $metadata = []
    ): AgentMessage
    {
        return $this->messages()->create([
            'role' => $role,
            'content' => $content,
            'message_type' => $type,
            'attachments' => $attachments ?: null,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'conversation_id');
    }

    // =========================================================================
    // Methods
    // =========================================================================

    public function transitionTo(ConversationPhase $phase): void
    {
        if (!$this->current_phase->canTransitionTo($phase)) {
            throw new InvalidArgumentException(
                "Cannot transition from {$this->current_phase->value} to {$phase->value}"
            );
        }

        $this->update([
            'current_phase' => $phase,
            'status' => $phase->isTerminal()
                ? ($phase === ConversationPhase::Completed ? 'completed' : 'failed')
                : 'active',
        ]);
    }

    public function forcePhase(ConversationPhase $phase): void
    {
        $this->update([
            'current_phase' => $phase,
            'status' => $phase->isTerminal()
                ? ($phase === ConversationPhase::Completed ? 'completed' : 'failed')
                : 'active',
        ]);
    }

    public function markFailed(?string $error = null): void
    {
        $metadata = $this->metadata ?? [];
        if ($error) {
            $metadata['last_error'] = $error;
            $metadata['failed_at'] = now()->toIso8601String();
        }

        $this->update([
            'current_phase' => ConversationPhase::Failed,
            'status' => 'failed',
            'metadata' => $metadata,
        ]);
    }

    public function markCompleted(): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['completed_at'] = now()->toIso8601String();

        $this->update([
            'current_phase' => ConversationPhase::Completed,
            'status' => 'completed',
            'metadata' => $metadata,
        ]);
    }

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    public function resume(): void
    {
        if ($this->status === 'paused') {
            $this->update(['status' => 'active']);
        }
    }

    /**
     * @param int $limit
     * @return array<array{role: string, content: string}>
     */
    public function getContextMessages(int $limit = 20): array
    {
        return $this->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->whereIn('message_type', [
                AgentMessageType::Text->value,
                AgentMessageType::Clarification->value,
            ])
            ->latest()
            ->take($limit)
            ->get()
            ->reverse()
            ->map(fn(AgentMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();
    }

    public function getLastUserMessage(): ?AgentMessage
    {
        return $this->messages()
            ->where('role', 'user')
            ->latest()
            ->first();
    }

    public function generateTitle(): string
    {
        $firstMessage = $this->messages()
            ->where('role', 'user')
            ->oldest()
            ->first();

        if (!$firstMessage) {
            return 'New Conversation';
        }

        $content = $firstMessage->content;
        $title = Str::limit($content, 50);

        if (strlen($content) > 50) {
            $lastSpace = strrpos($title, ' ');
            if ($lastSpace !== false && $lastSpace > 30) {
                $title = substr($title, 0, $lastSpace) . '...';
            }
        }

        $this->update(['title' => $title]);

        return $title;
    }

    public function updateContextSummary(array $summary): void
    {
        $this->update(['context_summary' => $summary]);
    }

    public function setCurrentIntent(IntentAnalysis $intent): void
    {
        $this->update(['current_intent_id' => $intent->id]);
    }

    public function setCurrentPlan(ExecutionPlan $plan): void
    {
        $this->update(['current_plan_id' => $plan->id]);
    }

    public function clearCurrentPlan(): void
    {
        $this->update(['current_plan_id' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toStateArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'phase' => [
                'value' => $this->current_phase->value,
                'label' => $this->current_phase->label(),
                'description' => $this->current_phase->description(),
                'icon' => $this->current_phase->icon(),
                'color' => $this->current_phase->color(),
            ],
            'requires_action' => $this->requires_user_action,
            'is_active' => $this->is_active,
            'has_plan' => $this->current_plan_id !== null,
            'has_intent' => $this->current_intent_id !== null,
            'message_count' => $this->messages()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function casts(): array
    {
        return [
            'current_phase' => ConversationPhase::class,
            'context_summary' => 'array',
            'metadata' => 'array',
        ];
    }
}
