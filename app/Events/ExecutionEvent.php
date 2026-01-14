<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JsonSerializable;

class ExecutionEvent implements ShouldBroadcast, JsonSerializable
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const TYPE_STARTED = 'started';
    public const TYPE_FILE_STARTED = 'file_started';
    public const TYPE_FILE_GENERATING = 'file_generating';
    public const TYPE_FILE_COMPLETED = 'file_completed';
    public const TYPE_FILE_FAILED = 'file_failed';
    public const TYPE_AWAITING_APPROVAL = 'awaiting_approval';
    public const TYPE_FILE_APPROVED = 'file_approved';
    public const TYPE_FILE_SKIPPED = 'file_skipped';
    public const TYPE_EXECUTION_STOPPED = 'execution_stopped';
    public const TYPE_COMPLETED = 'completed';
    public const TYPE_ROLLBACK_STARTED = 'rollback_started';
    public const TYPE_ROLLBACK_COMPLETED = 'rollback_completed';
    public const TYPE_ERROR = 'error';

    public readonly string $timestamp;

    public function __construct(
        public readonly string $type,
        public readonly array $data,
        ?string $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $planId = $this->data['plan_id'] ?? 'unknown';
        return [new PrivateChannel("execution.{$planId}")];
    }

    public function broadcastAs(): string
    {
        return 'execution.event';
    }

    public function broadcastWith(): array
    {
        return $this->toArray();
    }

    public static function started(string $planId, int $totalFiles): self
    {
        return new self(self::TYPE_STARTED, [
            'plan_id' => $planId,
            'total_files' => $totalFiles,
        ]);
    }

    public static function fileStarted(
        string $planId,
        int $index,
        string $path,
        string $operationType,
        ?string $description = null
    ): self {
        return new self(self::TYPE_FILE_STARTED, [
            'plan_id' => $planId,
            'index' => $index,
            'path' => $path,
            'type' => $operationType,
            'description' => $description,
        ]);
    }

    public static function fileGenerating(string $planId, int $index, string $path): self
    {
        return new self(self::TYPE_FILE_GENERATING, [
            'plan_id' => $planId,
            'index' => $index,
            'path' => $path,
        ]);
    }

    public static function fileCompleted(
        string $planId,
        int $index,
        string $path,
        ?string $diff = null
    ): self {
        return new self(self::TYPE_FILE_COMPLETED, [
            'plan_id' => $planId,
            'index' => $index,
            'path' => $path,
            'diff' => $diff,
            'success' => true,
        ]);
    }

    public static function fileFailed(
        string $planId,
        int $index,
        string $path,
        string $error
    ): self {
        return new self(self::TYPE_FILE_FAILED, [
            'plan_id' => $planId,
            'index' => $index,
            'path' => $path,
            'error' => $error,
            'success' => false,
        ]);
    }

    public static function awaitingApproval(
        string $planId,
        string $executionId,
        string $path,
        ?string $diff = null
    ): self {
        return new self(self::TYPE_AWAITING_APPROVAL, [
            'plan_id' => $planId,
            'execution_id' => $executionId,
            'path' => $path,
            'diff' => $diff,
        ]);
    }

    public static function fileApproved(string $planId, string $executionId, string $path): self
    {
        return new self(self::TYPE_FILE_APPROVED, [
            'plan_id' => $planId,
            'execution_id' => $executionId,
            'path' => $path,
        ]);
    }

    public static function fileSkipped(string $planId, int $index, string $path, string $reason): self
    {
        return new self(self::TYPE_FILE_SKIPPED, [
            'plan_id' => $planId,
            'index' => $index,
            'path' => $path,
            'reason' => $reason,
        ]);
    }

    public static function executionStopped(string $planId, string $reason, ?string $failedFile = null): self
    {
        return new self(self::TYPE_EXECUTION_STOPPED, [
            'plan_id' => $planId,
            'reason' => $reason,
            'failed_file' => $failedFile,
        ]);
    }

    public static function completed(string $planId, int $filesCompleted, int $filesFailed = 0): self
    {
        return new self(self::TYPE_COMPLETED, [
            'plan_id' => $planId,
            'files_completed' => $filesCompleted,
            'files_failed' => $filesFailed,
            'success' => $filesFailed === 0,
        ]);
    }

    public static function rollbackStarted(string $planId, int $filesToRollback): self
    {
        return new self(self::TYPE_ROLLBACK_STARTED, [
            'plan_id' => $planId,
            'files_to_rollback' => $filesToRollback,
        ]);
    }

    public static function rollbackCompleted(string $planId, int $rolledBack, int $failed): self
    {
        return new self(self::TYPE_ROLLBACK_COMPLETED, [
            'plan_id' => $planId,
            'rolled_back' => $rolledBack,
            'failed' => $failed,
            'success' => $failed === 0,
        ]);
    }

    public static function error(string $planId, string $message, ?string $context = null): self
    {
        return new self(self::TYPE_ERROR, [
            'plan_id' => $planId,
            'message' => $message,
            'context' => $context,
        ]);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
