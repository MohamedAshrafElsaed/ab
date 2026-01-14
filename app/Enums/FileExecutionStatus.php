<?php

namespace App\Enums;

enum FileExecutionStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case RolledBack = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
            self::RolledBack => 'Rolled Back',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Waiting to be executed',
            self::InProgress => 'Currently being executed',
            self::Completed => 'Successfully executed',
            self::Failed => 'Execution failed with an error',
            self::Skipped => 'Skipped by user or system',
            self::RolledBack => 'Changes have been rolled back',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Skipped,
            self::RolledBack,
        ]);
    }

    public function isActive(): bool
    {
        return $this === self::InProgress;
    }

    public function canRollback(): bool
    {
        return $this === self::Completed;
    }

    public function canRetry(): bool
    {
        return in_array($this, [
            self::Failed,
            self::RolledBack,
        ]);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Skipped => 'yellow',
            self::RolledBack => 'orange',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::InProgress => 'loader',
            self::Completed => 'check-circle',
            self::Failed => 'x-circle',
            self::Skipped => 'skip-forward',
            self::RolledBack => 'rotate-ccw',
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string>
     */
    public static function terminalStatuses(): array
    {
        return [
            self::Completed->value,
            self::Failed->value,
            self::Skipped->value,
            self::RolledBack->value,
        ];
    }
}
