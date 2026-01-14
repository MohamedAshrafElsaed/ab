<?php

namespace App\DTOs;

use JsonSerializable;

/**
 * Result of applying modifications to a file.
 */
readonly class ModificationResult implements JsonSerializable
{
    /**
     * @param array<array{section: string, type: string, applied: bool, explanation: string}> $changesApplied
     */
    public function __construct(
        public bool $success,
        public string $modifiedContent,
        public array $changesApplied,
        public string $diff,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    public static function success(
        string $modifiedContent,
        array $changesApplied,
        string $diff,
        array $metadata = []
    ): self {
        return new self(
            success: true,
            modifiedContent: $modifiedContent,
            changesApplied: $changesApplied,
            diff: $diff,
            error: null,
            metadata: $metadata,
        );
    }

    public static function failure(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            modifiedContent: '',
            changesApplied: [],
            diff: '',
            error: $error,
            metadata: $metadata,
        );
    }

    public function getTotalChanges(): int
    {
        return count($this->changesApplied);
    }

    public function getAppliedCount(): int
    {
        return count(array_filter($this->changesApplied, fn($c) => $c['applied'] ?? false));
    }

    public function getFailedCount(): int
    {
        return count(array_filter($this->changesApplied, fn($c) => !($c['applied'] ?? false)));
    }

    public function allChangesApplied(): bool
    {
        return $this->getFailedCount() === 0;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'modified_content_length' => strlen($this->modifiedContent),
            'changes_applied' => $this->changesApplied,
            'diff' => $this->diff,
            'error' => $this->error,
            'total_changes' => $this->getTotalChanges(),
            'applied_count' => $this->getAppliedCount(),
            'failed_count' => $this->getFailedCount(),
            'metadata' => $this->metadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
