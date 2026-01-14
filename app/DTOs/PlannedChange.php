<?php

namespace App\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Represents a specific change within a file modification.
 */
readonly class PlannedChange implements JsonSerializable
{
    public function __construct(
        public string $section,
        public string $changeType,
        public ?string $before,
        public string $after,
        public ?int $startLine,
        public ?int $endLine,
        public string $explanation,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        $validTypes = ['add', 'remove', 'replace'];
        if (!in_array($this->changeType, $validTypes, true)) {
            throw new InvalidArgumentException(
                "Invalid change type '{$this->changeType}'. Must be: " . implode(', ', $validTypes)
            );
        }

        if ($this->changeType === 'replace' && $this->before === null) {
            throw new InvalidArgumentException("Replace changes require 'before' content");
        }

        if ($this->changeType === 'remove' && $this->before === null) {
            throw new InvalidArgumentException("Remove changes require 'before' content");
        }
    }

    /**
     * Create an 'add' change.
     */
    public static function add(
        string $section,
        string $content,
        string $explanation,
        ?int $afterLine = null
    ): self {
        return new self(
            section: $section,
            changeType: 'add',
            before: null,
            after: $content,
            startLine: $afterLine,
            endLine: $afterLine,
            explanation: $explanation,
        );
    }

    /**
     * Create a 'remove' change.
     */
    public static function remove(
        string $section,
        string $content,
        string $explanation,
        ?int $startLine = null,
        ?int $endLine = null
    ): self {
        return new self(
            section: $section,
            changeType: 'remove',
            before: $content,
            after: '',
            startLine: $startLine,
            endLine: $endLine,
            explanation: $explanation,
        );
    }

    /**
     * Create a 'replace' change.
     */
    public static function replace(
        string $section,
        string $before,
        string $after,
        string $explanation,
        ?int $startLine = null,
        ?int $endLine = null
    ): self {
        return new self(
            section: $section,
            changeType: 'replace',
            before: $before,
            after: $after,
            startLine: $startLine,
            endLine: $endLine,
            explanation: $explanation,
        );
    }

    /**
     * Create from array (e.g., from JSON).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            section: $data['section'] ?? 'unknown',
            changeType: $data['change_type'] ?? $data['changeType'] ?? 'replace',
            before: $data['before'] ?? null,
            after: $data['after'] ?? '',
            startLine: $data['start_line'] ?? $data['startLine'] ?? null,
            endLine: $data['end_line'] ?? $data['endLine'] ?? null,
            explanation: $data['explanation'] ?? '',
        );
    }

    /**
     * Get estimated lines changed.
     */
    public function getEstimatedLinesChanged(): int
    {
        $beforeLines = $this->before ? substr_count($this->before, "\n") + 1 : 0;
        $afterLines = substr_count($this->after, "\n") + 1;

        return match ($this->changeType) {
            'add' => $afterLines,
            'remove' => $beforeLines,
            'replace' => max($beforeLines, $afterLines),
            default => 0,
        };
    }

    /**
     * Check if this is a significant change.
     */
    public function isSignificant(): bool
    {
        return $this->getEstimatedLinesChanged() > 5;
    }

    /**
     * Get a summary description.
     */
    public function getSummary(): string
    {
        $action = match ($this->changeType) {
            'add' => 'Add',
            'remove' => 'Remove',
            'replace' => 'Replace',
            default => 'Change',
        };

        $lines = $this->getEstimatedLinesChanged();
        return "{$action} ~{$lines} lines in {$this->section}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'section' => $this->section,
            'change_type' => $this->changeType,
            'before' => $this->before,
            'after' => $this->after,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
            'explanation' => $this->explanation,
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
