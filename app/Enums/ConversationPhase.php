<?php

namespace App\Enums;

enum ConversationPhase: string
{
    case Intake = 'intake';
    case Clarification = 'clarification';
    case Discovery = 'discovery';
    case Planning = 'planning';
    case Approval = 'approval';
    case Executing = 'executing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Intake => 'Receiving Request',
            self::Clarification => 'Gathering Details',
            self::Discovery => 'Analyzing Codebase',
            self::Planning => 'Creating Plan',
            self::Approval => 'Awaiting Approval',
            self::Executing => 'Making Changes',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Intake => 'Understanding what you want to do',
            self::Clarification => 'Need more information to proceed',
            self::Discovery => 'Finding relevant code and context',
            self::Planning => 'Designing the implementation approach',
            self::Approval => 'Review the plan before execution',
            self::Executing => 'Applying code changes',
            self::Completed => 'All changes applied successfully',
            self::Failed => 'An error occurred during processing',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Intake => 'message-circle',
            self::Clarification => 'help-circle',
            self::Discovery => 'search',
            self::Planning => 'file-text',
            self::Approval => 'check-circle',
            self::Executing => 'loader',
            self::Completed => 'check',
            self::Failed => 'x-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Intake => 'blue',
            self::Clarification => 'yellow',
            self::Discovery => 'indigo',
            self::Planning => 'purple',
            self::Approval => 'amber',
            self::Executing => 'cyan',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Discovery, self::Planning, self::Executing]);
    }

    public function requiresUserAction(): bool
    {
        return in_array($this, [self::Clarification, self::Approval]);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Intake => in_array($target, [self::Clarification, self::Discovery, self::Failed]),
            self::Clarification => in_array($target, [self::Intake, self::Discovery, self::Failed]),
            self::Discovery => in_array($target, [self::Planning, self::Failed]),
            self::Planning => in_array($target, [self::Approval, self::Failed]),
            self::Approval => in_array($target, [self::Executing, self::Planning, self::Failed]),
            self::Executing => in_array($target, [self::Completed, self::Failed]),
            self::Completed => false,
            self::Failed => in_array($target, [self::Intake, self::Planning]),
        };
    }

    /**
     * @return array<string>
     */
    public function nextPhases(): array
    {
        return match ($this) {
            self::Intake => ['clarification', 'discovery'],
            self::Clarification => ['intake', 'discovery'],
            self::Discovery => ['planning'],
            self::Planning => ['approval'],
            self::Approval => ['executing', 'planning'],
            self::Executing => ['completed'],
            self::Completed => [],
            self::Failed => ['intake', 'planning'],
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
