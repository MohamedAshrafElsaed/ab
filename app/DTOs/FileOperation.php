<?php

namespace App\DTOs;

use App\Enums\FileOperationType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Represents a file operation in an execution plan.
 */
readonly class FileOperation implements JsonSerializable
{
    /**
     * @param array<PlannedChange>|null $changes
     * @param array<string> $dependencies
     */
    public function __construct(
        public FileOperationType $type,
        public string $path,
        public ?string $newPath,
        public ?string $description,
        public ?array $changes,
        public ?string $templateContent,
        public int $priority,
        public array $dependencies,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->path)) {
            throw new InvalidArgumentException('File path cannot be empty');
        }

        if ($this->type->requiresNewPath() && empty($this->newPath)) {
            throw new InvalidArgumentException(
                "Operation type '{$this->type->value}' requires a new path"
            );
        }

        if ($this->type === FileOperationType::Create && empty($this->templateContent)) {
            throw new InvalidArgumentException('Create operations require template content');
        }

        if ($this->type === FileOperationType::Modify && empty($this->changes)) {
            throw new InvalidArgumentException('Modify operations require changes array');
        }
    }

    /**
     * Create a new file operation.
     */
    public static function create(
        string $path,
        string $content,
        string $description,
        int $priority = 1,
        array $dependencies = []
    ): self {
        return new self(
            type: FileOperationType::Create,
            path: $path,
            newPath: null,
            description: $description,
            changes: null,
            templateContent: $content,
            priority: $priority,
            dependencies: $dependencies,
        );
    }

    /**
     * Create a modify file operation.
     *
     * @param array<PlannedChange> $changes
     * @param array<string> $dependencies
     */
    public static function modify(
        string $path,
        array $changes,
        string $description,
        int $priority = 1,
        array $dependencies = []
    ): self {
        return new self(
            type: FileOperationType::Modify,
            path: $path,
            newPath: null,
            description: $description,
            changes: $changes,
            templateContent: null,
            priority: $priority,
            dependencies: $dependencies,
        );
    }

    /**
     * Create a delete file operation.
     *
     * @param array<string> $dependencies
     */
    public static function delete(
        string $path,
        string $reason,
        int $priority = 1,
        array $dependencies = []
    ): self {
        return new self(
            type: FileOperationType::Delete,
            path: $path,
            newPath: null,
            description: $reason,
            changes: null,
            templateContent: null,
            priority: $priority,
            dependencies: $dependencies,
        );
    }

    /**
     * Create a rename file operation.
     *
     * @param array<string> $dependencies
     */
    public static function rename(
        string $path,
        string $newPath,
        string $description,
        int $priority = 1,
        array $dependencies = []
    ): self {
        return new self(
            type: FileOperationType::Rename,
            path: $path,
            newPath: $newPath,
            description: $description,
            changes: null,
            templateContent: null,
            priority: $priority,
            dependencies: $dependencies,
        );
    }

    /**
     * Create a move file operation.
     *
     * @param array<string> $dependencies
     */
    public static function move(
        string $path,
        string $newPath,
        string $description,
        int $priority = 1,
        array $dependencies = []
    ): self {
        return new self(
            type: FileOperationType::Move,
            path: $path,
            newPath: $newPath,
            description: $description,
            changes: null,
            templateContent: null,
            priority: $priority,
            dependencies: $dependencies,
        );
    }

    /**
     * Create from array (e.g., from JSON).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = is_string($data['type'])
            ? FileOperationType::from($data['type'])
            : $data['type'];

        $changes = null;
        if (!empty($data['changes']) && is_array($data['changes'])) {
            $changes = array_map(
                fn($c) => $c instanceof PlannedChange ? $c : PlannedChange::fromArray($c),
                $data['changes']
            );
        }

        return new self(
            type: $type,
            path: $data['path'],
            newPath: $data['new_path'] ?? $data['newPath'] ?? null,
            description: $data['description'] ?? null,
            changes: $changes,
            templateContent: $data['template_content'] ?? $data['templateContent'] ?? null,
            priority: $data['priority'] ?? 1,
            dependencies: $data['dependencies'] ?? [],
        );
    }

    /**
     * Get the file extension.
     */
    public function getExtension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /**
     * Get the directory.
     */
    public function getDirectory(): string
    {
        $dir = dirname($this->path);
        return $dir === '.' ? '' : $dir;
    }

    /**
     * Get the filename without extension.
     */
    public function getBasename(): string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * Check if this operation depends on another path.
     */
    public function dependsOn(string $path): bool
    {
        return in_array($path, $this->dependencies, true);
    }

    /**
     * Get estimated lines affected.
     */
    public function getEstimatedLinesAffected(): int
    {
        if ($this->templateContent) {
            return substr_count($this->templateContent, "\n") + 1;
        }

        if ($this->changes) {
            return array_sum(array_map(
                fn(PlannedChange $c) => $c->getEstimatedLinesChanged(),
                $this->changes
            ));
        }

        return 0;
    }

    /**
     * Get a short summary.
     */
    public function getSummary(): string
    {
        $action = $this->type->label();
        $file = basename($this->path);

        if ($this->newPath) {
            return "{$action}: {$file} → " . basename($this->newPath);
        }

        $lines = $this->getEstimatedLinesAffected();
        return "{$action}: {$file}" . ($lines > 0 ? " (~{$lines} lines)" : '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'path' => $this->path,
            'new_path' => $this->newPath,
            'description' => $this->description,
            'changes' => $this->changes
                ? array_map(fn(PlannedChange $c) => $c->toArray(), $this->changes)
                : null,
            'template_content' => $this->templateContent,
            'priority' => $this->priority,
            'dependencies' => $this->dependencies,
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
