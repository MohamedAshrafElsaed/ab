<?php

namespace App\DTOs;

use JsonSerializable;

/**
 * Result of a rollback operation.
 */
readonly class RollbackResult implements JsonSerializable
{
    /**
     * @param array<string> $rolledBack Successfully rolled back file paths
     * @param array<array{path: string, error: string}> $failed Failed rollback attempts
     * @param array<string> $skipped Files that were skipped (no backup, etc.)
     */
    public function __construct(
        public bool $success,
        public array $rolledBack,
        public array $failed,
        public array $skipped,
        public array $metadata = [],
    ) {}

    public static function fromResults(
        array $rolledBack,
        array $failed,
        array $skipped,
        array $metadata = []
    ): self {
        return new self(
            success: empty($failed),
            rolledBack: $rolledBack,
            failed: $failed,
            skipped: $skipped,
            metadata: $metadata,
        );
    }

    public static function empty(): self
    {
        return new self(
            success: true,
            rolledBack: [],
            failed: [],
            skipped: [],
        );
    }

    public function getRolledBackCount(): int
    {
        return count($this->rolledBack);
    }

    public function getFailedCount(): int
    {
        return count($this->failed);
    }

    public function getSkippedCount(): int
    {
        return count($this->skipped);
    }

    public function getTotalAttempted(): int
    {
        return $this->getRolledBackCount() + $this->getFailedCount();
    }

    public function isPartialSuccess(): bool
    {
        return !$this->success && $this->getRolledBackCount() > 0;
    }

    public function isComplete(): bool
    {
        return $this->success && $this->getSkippedCount() === 0;
    }

    public function getFailedPaths(): array
    {
        return array_column($this->failed, 'path');
    }

    public function getFailureMessages(): array
    {
        $messages = [];
        foreach ($this->failed as $failure) {
            $messages[$failure['path']] = $failure['error'];
        }
        return $messages;
    }

    public function getSummary(): string
    {
        $parts = [];

        if ($this->getRolledBackCount() > 0) {
            $parts[] = "{$this->getRolledBackCount()} rolled back";
        }
        if ($this->getFailedCount() > 0) {
            $parts[] = "{$this->getFailedCount()} failed";
        }
        if ($this->getSkippedCount() > 0) {
            $parts[] = "{$this->getSkippedCount()} skipped";
        }

        return implode(', ', $parts) ?: 'No changes to rollback';
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'rolled_back' => $this->rolledBack,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            'summary' => $this->getSummary(),
            'counts' => [
                'rolled_back' => $this->getRolledBackCount(),
                'failed' => $this->getFailedCount(),
                'skipped' => $this->getSkippedCount(),
            ],
            'metadata' => $this->metadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
