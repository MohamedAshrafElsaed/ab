<?php

namespace App\DTOs;

use JsonSerializable;

/**
 * Result of executing a single file operation.
 */
readonly class FileExecutionResult implements JsonSerializable
{
    public function __construct(
        public bool $success,
        public ?string $newContent = null,
        public ?string $diff = null,
        public ?string $error = null,
        public ?string $backupPath = null,
        public array $metadata = [],
    ) {}

    public static function success(
        ?string $newContent = null,
        ?string $diff = null,
        ?string $backupPath = null,
        array $metadata = []
    ): self {
        return new self(
            success: true,
            newContent: $newContent,
            diff: $diff,
            error: null,
            backupPath: $backupPath,
            metadata: $metadata,
        );
    }

    public static function failure(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            newContent: null,
            diff: null,
            error: $error,
            backupPath: null,
            metadata: $metadata,
        );
    }

    public static function skipped(string $reason, array $metadata = []): self
    {
        return new self(
            success: true,
            newContent: null,
            diff: null,
            error: null,
            backupPath: null,
            metadata: array_merge($metadata, ['skipped' => true, 'skip_reason' => $reason]),
        );
    }

    public function wasSkipped(): bool
    {
        return $this->metadata['skipped'] ?? false;
    }

    public function hasBackup(): bool
    {
        return $this->backupPath !== null;
    }

    public function hasDiff(): bool
    {
        return !empty($this->diff);
    }

    public function getContentLength(): int
    {
        return $this->newContent !== null ? strlen($this->newContent) : 0;
    }

    public function getDiffLineCount(): int
    {
        if (empty($this->diff)) {
            return 0;
        }
        return substr_count($this->diff, "\n") + 1;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'has_content' => $this->newContent !== null,
            'content_length' => $this->getContentLength(),
            'has_diff' => $this->hasDiff(),
            'diff_lines' => $this->getDiffLineCount(),
            'error' => $this->error,
            'backup_path' => $this->backupPath,
            'metadata' => $this->metadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
