<?php

namespace App\DTOs;

use App\Enums\ConversationPhase;
use App\Models\AgentConversation;
use App\Models\ExecutionPlan;
use App\Models\FileExecution;
use App\Models\IntentAnalysis;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class ConversationState implements JsonSerializable
{
    /**
     * @param Collection<int, FileExecution>|null $pendingExecutions
     * @param array<string> $availableActions
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $conversationId,
        public ConversationPhase $phase,
        public string $status,
        public ?string $title,
        public ?IntentAnalysis $currentIntent,
        public ?ExecutionPlan $currentPlan,
        public ?Collection $pendingExecutions,
        public array $availableActions,
        public array $metadata = [],
    ) {}

    public static function fromConversation(AgentConversation $conversation): self
    {
        $plan = $conversation->currentPlan;
        $pendingExecutions = null;

        if ($plan && $conversation->current_phase === ConversationPhase::Executing) {
            $pendingExecutions = $plan->fileExecutions()
                ->whereIn('status', ['pending', 'in_progress'])
                ->get();
        }

        return new self(
            conversationId: $conversation->id,
            phase: $conversation->current_phase,
            status: $conversation->status,
            title: $conversation->title,
            currentIntent: $conversation->currentIntent,
            currentPlan: $plan,
            pendingExecutions: $pendingExecutions,
            availableActions: self::getAvailableActions($conversation),
            metadata: [
                'message_count' => $conversation->messages()->count(),
                'created_at' => $conversation->created_at?->toIso8601String(),
                'updated_at' => $conversation->updated_at?->toIso8601String(),
                'context_summary' => $conversation->context_summary,
            ],
        );
    }

    /**
     * @return array<string>
     */
    private static function getAvailableActions(AgentConversation $conversation): array
    {
        $actions = [];

        if ($conversation->status === 'paused') {
            $actions[] = 'resume';
            return $actions;
        }

        if ($conversation->current_phase->isTerminal()) {
            $actions[] = 'new_conversation';
            if ($conversation->current_phase === ConversationPhase::Failed) {
                $actions[] = 'retry';
            }
            return $actions;
        }

        $actions[] = 'send_message';
        $actions[] = 'cancel';

        if ($conversation->current_phase === ConversationPhase::Approval) {
            $actions[] = 'approve_plan';
            $actions[] = 'reject_plan';
            $actions[] = 'request_changes';
        }

        if ($conversation->current_phase === ConversationPhase::Executing) {
            $actions[] = 'approve_file';
            $actions[] = 'skip_file';
            $actions[] = 'rollback';
        }

        return $actions;
    }

    public function isAwaitingApproval(): bool
    {
        return $this->phase === ConversationPhase::Approval;
    }

    public function isExecuting(): bool
    {
        return $this->phase === ConversationPhase::Executing;
    }

    public function isTerminal(): bool
    {
        return $this->phase->isTerminal();
    }

    public function hasPlan(): bool
    {
        return $this->currentPlan !== null;
    }

    public function hasIntent(): bool
    {
        return $this->currentIntent !== null;
    }

    public function canSendMessage(): bool
    {
        return in_array('send_message', $this->availableActions);
    }

    public function canApprove(): bool
    {
        return in_array('approve_plan', $this->availableActions)
            || in_array('approve_file', $this->availableActions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'phase' => [
                'value' => $this->phase->value,
                'label' => $this->phase->label(),
                'description' => $this->phase->description(),
                'icon' => $this->phase->icon(),
                'color' => $this->phase->color(),
                'is_terminal' => $this->phase->isTerminal(),
                'requires_action' => $this->phase->requiresUserAction(),
            ],
            'status' => $this->status,
            'title' => $this->title,
            'has_intent' => $this->hasIntent(),
            'has_plan' => $this->hasPlan(),
            'intent' => $this->currentIntent?->toSummaryArray(),
            'plan' => $this->currentPlan ? [
                'id' => $this->currentPlan->id,
                'title' => $this->currentPlan->title,
                'status' => $this->currentPlan->status->value,
                'files_affected' => $this->currentPlan->estimated_files_affected,
                'complexity' => $this->currentPlan->estimated_complexity?->value,
            ] : null,
            'pending_executions' => $this->pendingExecutions?->map(fn($e) => [
                'id' => $e->id,
                'path' => $e->file_path,
                'type' => $e->operation_type->value,
                'status' => $e->status->value,
            ])->toArray(),
            'available_actions' => $this->availableActions,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
