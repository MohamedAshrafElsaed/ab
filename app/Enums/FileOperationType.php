<?php

namespace App\Enums;

enum FileOperationType: string
{
    case Create = 'create';
    case Modify = 'modify';
    case Delete = 'delete';
    case Rename = 'rename';
    case Move = 'move';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create',
            self::Modify => 'Modify',
            self::Delete => 'Delete',
            self::Rename => 'Rename',
            self::Move => 'Move',
        };
    }

    /**
     * Get description of operation.
     */
    public function description(): string
    {
        return match ($this) {
            self::Create => 'Create a new file with specified content',
            self::Modify => 'Modify existing file with specified changes',
            self::Delete => 'Delete an existing file',
            self::Rename => 'Rename a file within the same directory',
            self::Move => 'Move a file to a different directory',
        };
    }

    /**
     * Check if operation requires existing file.
     */
    public function requiresExistingFile(): bool
    {
        return match ($this) {
            self::Create => false,
            self::Modify, self::Delete, self::Rename, self::Move => true,
        };
    }

    /**
     * Check if operation requires content.
     */
    public function requiresContent(): bool
    {
        return match ($this) {
            self::Create, self::Modify => true,
            self::Delete, self::Rename, self::Move => false,
        };
    }

    /**
     * Check if operation requires a new path.
     */
    public function requiresNewPath(): bool
    {
        return match ($this) {
            self::Rename, self::Move => true,
            self::Create, self::Modify, self::Delete => false,
        };
    }

    /**
     * Check if this is a destructive operation.
     */
    public function isDestructive(): bool
    {
        return match ($this) {
            self::Delete => true,
            self::Modify, self::Rename, self::Move => false,
            self::Create => false,
        };
    }

    /**
     * Get risk level for this operation.
     */
    public function riskLevel(): string
    {
        return match ($this) {
            self::Delete => 'high',
            self::Modify, self::Move => 'medium',
            self::Create, self::Rename => 'low',
        };
    }

    /**
     * Get icon name for UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Create => 'plus-circle',
            self::Modify => 'pencil',
            self::Delete => 'trash',
            self::Rename => 'tag',
            self::Move => 'folder-arrow-right',
        };
    }

    /**
     * Get color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Create => 'green',
            self::Modify => 'blue',
            self::Delete => 'red',
            self::Rename => 'yellow',
            self::Move => 'purple',
        };
    }

    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
