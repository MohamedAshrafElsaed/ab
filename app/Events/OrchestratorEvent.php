<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JsonSerializable;

class OrchestratorEvent implements ShouldBroadcast, JsonSerializable
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Phase transition events
    public const string TYPE_MESSAGE_RECEIVED = 'message_received';
    public const string TYPE_PHASE_CHANGED = 'phase_changed';

    // Intent analysis events
    public const string TYPE_ANALYZING_INTENT = 'analyzing_intent';
    public const string TYPE_INTENT_ANALYZED = 'intent_analyzed';
    public const string TYPE_CLARIFICATION_NEEDED = 'clarification_needed';

    // Context retrieval events
    public const string TYPE_RETRIEVING_CONTEXT = 'retrieving_context';
    public const string TYPE_CONTEXT_RETRIEVED = 'context_retrieved';

    // Planning events
    public const string TYPE_GENERATING_PLAN = 'generating_plan';
    public const string TYPE_PLAN_GENERATED = 'plan_generated';
    public const string TYPE_AWAITING_APPROVAL = 'awaiting_approval';
    public const string TYPE_PLAN_APPROVED = 'plan_approved';
    public const string TYPE_PLAN_REJECTED = 'plan_rejected';

    // Execution events (delegated to ExecutionEvent, but wrapped here)
    public const string TYPE_EXECUTION_STARTED = 'execution_started';
    public const string TYPE_EXECUTION_PROGRESS = 'execution_progress';
    public const string TYPE_FILE_APPROVAL_NEEDED = 'file_approval_needed';
    public const string TYPE_EXECUTION_COMPLETED = 'execution_completed';

    // Response events
    public const string TYPE_RESPONSE_CHUNK = 'response_chunk';
    public const string TYPE_RESPONSE_COMPLETE = 'response_complete';

    // Error events
    public const string TYPE_ERROR = 'error';
    public const string TYPE_CANCELLED = 'cancelled';

    public readonly string $timestamp;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data,
        public readonly ?string $conversationId = null,
        ?string $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if ($this->conversationId) {
            return [new PrivateChannel("conversation.{$this->conversationId}")];
        }

        return [];
    }

    public function broadcastAs(): string
    {
        return 'orchestrator.event';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->toArray();
    }

    // =========================================================================
    // Factory Methods - Phase Transitions
    // =========================================================================

    public static function messageReceived(string $conversationId, string $message): self
    {
        return new self(self::TYPE_MESSAGE_RECEIVED, [
            'conversation_id' => $conversationId,
            'message' => $message,
        ], $conversationId);
    }

    public static function phaseChanged(string $conversationId, string $from, string $to): self
    {
        return new self(self::TYPE_PHASE_CHANGED, [
            'conversation_id' => $conversationId,
            'from_phase' => $from,
            'to_phase' => $to,
        ], $conversationId);
    }

    // =========================================================================
    // Factory Methods - Intent Analysis
    // =========================================================================

    public static function analyzingIntent(string $conversationId): self
    {
        return new self(self::TYPE_ANALYZING_INTENT, [
            'conversation_id' => $conversationId,
            'status' => 'Analyzing your request...',
        ], $conversationId);
    }

    public static function intentAnalyzed(
        string $conversationId,
        string $intentType,
        float $confidence,
        ?string $summary = null
    ): self {
        return new self(self::TYPE_INTENT_ANALYZED, [
            'conversation_id' => $conversationId,
            'intent_type' => $intentType,
            'confidence' => $confidence,
            'summary' => $summary,
        ], $conversationId);
    }

    /**
     * @param array<string> $questions
     */
    public static function clarificationNeeded(string $conversationId, array $questions): self
    {
        return new self(self::TYPE_CLARIFICATION_NEEDED, [
            'conversation_id' => $conversationId,
            'questions' => $questions,
        ], $conversationId);
    }

    // =========================================================================
    // Factory Methods - Context Retrieval
    // =========================================================================

    public static function retrievingContext(string $conversationId): self
    {
        return new self(self::TYPE_RETRIEVING_CONTEXT, [
            'conversation_id' => $conversationId,
            'status' => 'Searching relevant code...',
        ], $conversationId);
    }

    public static function contextRetrieved(string $conversationId, int $filesFound, int $chunksFound): self
    {
        return new self(self::TYPE_CONTEXT_RETRIEVED, [
            'conversation_id' => $conversationId,
            'files_found' => $filesFound,
            'chunks_found' => $chunksFound,
        ], $conversationId);
    }

    // =========================================================================
    // Factory Methods - Planning
    // =========================================================================

    public static function generatingPlan(string $conversationId): self
    {
        return new self(self::TYPE_GENERATING_PLAN, [
            'conversation_id' => $conversationId,
            'status' => 'Creating implementation plan...',
        ], $conversationId);
    }

    public static function planGenerated(
        string $conversationId,
        string $planId,
        string $title,
        int $filesAffected
    ): self {
        return new self(self::TYPE_PLAN_GENERATED, [
            'conversation_id' => $conversationId,
            'plan_id' => $planId,
            'title' => $title,
            'files_affected' => $filesAffected,
        ], $conversationId);
    }

    public static function awaitingApproval(string $conversationId, string $planId): self
    {
        return new self(self::TYPE_AWAITING_APPROVAL, [
            'conversation_id' => $conversationId,
            'plan_id' => $planId,
        ], $conversationId);
    }

    public static function planApproved(string $conversationId, string $planId): self
    {
        return new self(self::TYPE_PLAN_APPROVED, [
            'conversation_id' => $conversationId,
            'plan_id' => $planId,
        ], $conversationId);
    }

    public static function planRejected(string $conversationId, string $planId, ?string $reason = null): self
    {
        return new self(self::TYPE_PLAN_REJECTED, [
            'conversation_id' => $conversationId,
            'plan_id' => $planId,
            'reason' => $reason,
        ], $conversationId);
    }

    // =========================================================================
    // Factory Methods - Execution
    // =========================================================================

    public static function executionStarted(string $conversationId, string $planId, int $totalFiles): self
    {
        return new self(self::TYPE_EXECUTION_STARTED, [
            'conversation_id' => $conversationId,
            'plan_id' => $planId,
            'total_files' => $totalFiles,
        ], $conversationId);
    }

    public static function executionProgress(
        string $conversationId,
        int $completed,
        int $total,
        ?string $currentFile = null
    ): self {
        return new self(self::TYPE_EXECUTION_PROGRESS, [
            'conversation_id' => $conversationId,
            'completed' => $completed,
            'total' => $total,
            'current_file' => $currentFile,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ], $conversationId);
    }

    public static function fileApprovalNeeded(
        string $conversationId,
        string $executionId,
        string $path,
        ?string $diff = null
    ): self {
        return new self(self::TYPE_FILE_APPROVAL_NEEDED, [
            'conversation_id' => $conversationId,
            'execution_id' => $executionId,
            'path' => $path,
            'diff' => $diff,
        ], $conversationId);
    }

    public static function executionCompleted(
        string $conversationId,
        int $filesCompleted,
        int $filesFailed
    ): self {
        return new self(self::TYPE_EXECUTION_COMPLETED, [
            'conversation_id' => $conversationId,
            'files_completed' => $filesCompleted,
            'files_failed' => $filesFailed,
            'success' => $filesFailed === 0,
        ], $conversationId);
    }

    // =========================================================================
    // Factory Methods - Response Streaming
    // =========================================================================

    public static function responseChunk(string $conversationId, string $chunk): self
    {
        return new self(self::TYPE_RESPONSE_CHUNK, [
            'conversation_id' => $conversationId,
            'chunk' => $chunk,
        ], $conversationId);
    }

    public static function responseComplete(string $conversationId, ?string $messageId = null): self
    {
        return new self(self::TYPE_RESPONSE_COMPLETE, [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
        ], $conversationId);
    }

    // =========================================================================
    // Factory Methods - Errors
    // =========================================================================

    public static function error(string $conversationId, string $error, ?string $phase = null): self
    {
        return new self(self::TYPE_ERROR, [
            'conversation_id' => $conversationId,
            'error' => $error,
            'phase' => $phase,
        ], $conversationId);
    }

    public static function cancelled(string $conversationId, ?string $reason = null): self
    {
        return new self(self::TYPE_CANCELLED, [
            'conversation_id' => $conversationId,
            'reason' => $reason,
        ], $conversationId);
    }

    // =========================================================================
    // Serialization
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'conversation_id' => $this->conversationId,
            'timestamp' => $this->timestamp,
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
