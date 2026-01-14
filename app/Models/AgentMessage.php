<?php

namespace App\Models;

use App\Enums\AgentMessageType;
use Database\Factories\AgentMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $role
 * @property string $content
 * @property AgentMessageType $message_type
 * @property array<string, mixed>|null $attachments
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property-read AgentConversation $conversation
 * @property-read bool $is_user
 * @property-read bool $is_assistant
 * @property-read bool $is_system
 * @property-read bool $requires_action
 * @property-read string $formatted_content
 */
class AgentMessage extends Model
{
    /** @use HasFactory<AgentMessageFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'message_type',
        'attachments',
        'metadata',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AgentMessage $message) {
            if (!$message->created_at) {
                $message->created_at = now();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id');
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * @param Builder<AgentMessage> $query
     * @return Builder<AgentMessage>
     */
    public function scopeFromUser(Builder $query): Builder
    {
        return $query->where('role', 'user');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * @param Builder<AgentMessage> $query
     * @return Builder<AgentMessage>
     */
    public function scopeFromAssistant(Builder $query): Builder
    {
        return $query->where('role', 'assistant');
    }

    /**
     * @param Builder<AgentMessage> $query
     * @return Builder<AgentMessage>
     */
    public function scopeOfType(Builder $query, AgentMessageType $type): Builder
    {
        return $query->where('message_type', $type->value);
    }

    /**
     * @param Builder<AgentMessage> $query
     * @return Builder<AgentMessage>
     */
    public function scopeRequiringAction(Builder $query): Builder
    {
        return $query->whereIn('message_type', [
            AgentMessageType::ApprovalRequest->value,
            AgentMessageType::Clarification->value,
        ]);
    }

    /**
     * @param Builder<AgentMessage> $query
     * @return Builder<AgentMessage>
     */
    public function scopeTextOnly(Builder $query): Builder
    {
        return $query->where('message_type', AgentMessageType::Text->value);
    }

    public function getIsUserAttribute(): bool
    {
        return $this->role === 'user';
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function getIsAssistantAttribute(): bool
    {
        return $this->role === 'assistant';
    }

    public function getIsSystemAttribute(): bool
    {
        return $this->role === 'system';
    }

    public function getRequiresActionAttribute(): bool
    {
        return $this->message_type->requiresAction();
    }

    public function getFormattedContentAttribute(): string
    {
        return match ($this->message_type) {
            AgentMessageType::PlanPreview => $this->formatPlanContent(),
            AgentMessageType::FileDiff => $this->formatDiffContent(),
            AgentMessageType::ExecutionUpdate => $this->formatExecutionContent(),
            default => $this->content,
        };
    }

    private function formatPlanContent(): string
    {
        $attachments = $this->attachments ?? [];
        $planId = $attachments['plan_id'] ?? null;

        if ($planId) {
            return $this->content . "\n\n[Plan ID: {$planId}]";
        }

        return $this->content;
    }

    private function formatDiffContent(): string
    {
        $attachments = $this->attachments ?? [];
        $diff = $attachments['diff'] ?? null;
        $path = $attachments['path'] ?? 'unknown';

        if ($diff) {
            return "**File: {$path}**\n\n```diff\n{$diff}\n```";
        }

        return $this->content;
    }

    // =========================================================================
    // Methods
    // =========================================================================

    private function formatExecutionContent(): string
    {
        $attachments = $this->attachments ?? [];
        $progress = $attachments['progress'] ?? null;
        $total = $attachments['total'] ?? null;

        if ($progress !== null && $total !== null) {
            return $this->content . " ({$progress}/{$total})";
        }

        return $this->content;
    }

    public function getAttachmentsCollectionAttribute(): Collection
    {
        return collect($this->attachments ?? []);
    }

    public function hasAttachment(string $key): bool
    {
        return isset($this->attachments[$key]);
    }

    public function getAttachment(string $key, mixed $default = null): mixed
    {
        return $this->attachments[$key] ?? $default;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiFormat(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'type' => [
                'value' => $this->message_type->value,
                'label' => $this->message_type->label(),
                'icon' => $this->message_type->icon(),
                'color' => $this->message_type->color(),
            ],
            'attachments' => $this->attachments,
            'requires_action' => $this->requires_action,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    protected function casts(): array
    {
        return [
            'message_type' => AgentMessageType::class,
            'attachments' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
