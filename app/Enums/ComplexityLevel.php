<?php

namespace App\Enums;

enum ComplexityLevel: string
{
    case Trivial = 'trivial';
    case Simple = 'simple';
    case Medium = 'medium';
    case Complex = 'complex';
    case Major = 'major';

    /**
     * Get a human-readable label for the complexity level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trivial => 'Trivial',
            self::Simple => 'Simple',
            self::Medium => 'Medium',
            self::Complex => 'Complex',
            self::Major => 'Major',
        };
    }

    /**
     * Get a description of what this complexity level means.
     */
    public function description(): string
    {
        return match ($this) {
            self::Trivial => 'Quick change, single file, minimal impact',
            self::Simple => 'Straightforward change, few files, low risk',
            self::Medium => 'Moderate effort, multiple files, some testing required',
            self::Complex => 'Significant effort, many files, careful planning needed',
            self::Major => 'Large-scale change, architectural impact, extensive testing',
        };
    }

    /**
     * Get estimated time range in hours.
     *
     * @return array{min: float, max: float}
     */
    public function estimatedHours(): array
    {
        return match ($this) {
            self::Trivial => ['min' => 0.1, 'max' => 0.5],
            self::Simple => ['min' => 0.5, 'max' => 2.0],
            self::Medium => ['min' => 2.0, 'max' => 8.0],
            self::Complex => ['min' => 8.0, 'max' => 24.0],
            self::Major => ['min' => 24.0, 'max' => 80.0],
        };
    }

    /**
     * Get estimated number of files affected.
     *
     * @return array{min: int, max: int}
     */
    public function estimatedFilesAffected(): array
    {
        return match ($this) {
            self::Trivial => ['min' => 1, 'max' => 1],
            self::Simple => ['min' => 1, 'max' => 3],
            self::Medium => ['min' => 3, 'max' => 10],
            self::Complex => ['min' => 10, 'max' => 25],
            self::Major => ['min' => 25, 'max' => 100],
        };
    }

    /**
     * Get the numeric weight for sorting/comparison.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Trivial => 1,
            self::Simple => 2,
            self::Medium => 3,
            self::Complex => 4,
            self::Major => 5,
        };
    }

    /**
     * Check if this complexity level is higher than another.
     */
    public function isHigherThan(self $other): bool
    {
        return $this->weight() > $other->weight();
    }

    /**
     * Get all values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Create from a numeric score (0-1).
     */
    public static function fromScore(float $score): self
    {
        return match (true) {
            $score < 0.2 => self::Trivial,
            $score < 0.4 => self::Simple,
            $score < 0.6 => self::Medium,
            $score < 0.8 => self::Complex,
            default => self::Major,
        };
    }
}
