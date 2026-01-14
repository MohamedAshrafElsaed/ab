<?php

namespace App\DTOs;

use JsonSerializable;

/**
 * Result of a file write operation.
 */
readonly class WriteResult implements JsonSerializable
{
    public function __construct(
        public bool $success,
        public string $path,
        public ?string $backupPath = null,
        public ?string $error = null,
        public ?string $originalContent = null,
        public array $metadata = [],
    ) {}

    public static function success(
        string $path,
        ?string $backupPath = null,
        ?string $originalContent = null,
        array $metadata = []
    ): self {
        return new self(
            success: true,
            path: $path,
            backupPath: $backupPath,
            error: null,
            originalContent: $originalContent,
            metadata: $metadata,
        );
    }

    public static function failure(string $path, string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            path: $path,
            backupPath: null,
            error: $error,
            originalContent: null,
            metadata: $metadata,
        );
    }

    public function hasBackup(): bool
    {
        return $this->backupPath !== null && file_exists($this->backupPath);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'path' => $this->path,
            'backup_path' => $this->backupPath,
            'error' => $this->error,
            'has_original' => $this->originalContent !== null,
            'metadata' => $this->metadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
