<?php

namespace App\Enums;

enum PlanStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executing = 'executing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Executing => 'Executing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get description of status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Plan is being generated or refined',
            self::PendingReview => 'Plan is ready for user review',
            self::Approved => 'Plan has been approved for execution',
            self::Rejected => 'Plan was rejected by user',
            self::Executing => 'Plan is currently being executed',
            self::Completed => 'Plan execution completed successfully',
            self::Failed => 'Plan execution failed',
        };
    }

    /**
     * Check if status allows modification.
     */
    public function isModifiable(): bool
    {
        return match ($this) {
            self::Draft, self::PendingReview, self::Rejected => true,
            self::Approved, self::Executing, self::Completed, self::Failed => false,
        };
    }

    /**
     * Check if status allows execution.
     */
    public function canExecute(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Check if this is a terminal status.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Rejected => true,
            default => false,
        };
    }

    /**
     * Get valid transition targets.
     *
     * @return array<PlanStatus>
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview, self::Rejected],
            self::PendingReview => [self::Approved, self::Rejected, self::Draft],
            self::Approved => [self::Executing, self::Rejected],
            self::Rejected => [self::Draft],
            self::Executing => [self::Completed, self::Failed],
            self::Completed => [],
            self::Failed => [self::Draft, self::Approved],
        };
    }

    /**
     * Check if transition to target status is valid.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->validTransitions(), true);
    }

    /**
     * Get badge color for UI.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingReview => 'yellow',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Executing => 'purple',
            self::Completed => 'green',
            self::Failed => 'red',
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
