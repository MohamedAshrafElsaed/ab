<?php

namespace App\DTOs;

use JsonSerializable;

/**
 * Risk assessment for an execution plan.
 */
readonly class RiskAssessment implements JsonSerializable
{
    /**
     * @param array<array{level: string, description: string, mitigation: ?string}> $risks
     * @param array<string> $prerequisites
     * @param array<string> $manualSteps
     */
    public function __construct(
        public string $overallLevel,
        public array $risks,
        public array $prerequisites,
        public bool $requiresManualSteps,
        public array $manualSteps,
    ) {}

    /**
     * Create a low-risk assessment.
     *
     * @param array<string> $prerequisites
     */
    public static function low(array $prerequisites = []): self
    {
        return new self(
            overallLevel: 'low',
            risks: [],
            prerequisites: $prerequisites,
            requiresManualSteps: false,
            manualSteps: [],
        );
    }

    /**
     * Create assessment from array (e.g., from JSON).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            overallLevel: $data['overall_level'] ?? $data['overallLevel'] ?? 'medium',
            risks: $data['risks'] ?? [],
            prerequisites: $data['prerequisites'] ?? [],
            requiresManualSteps: $data['requires_manual_steps'] ?? $data['requiresManualSteps'] ?? false,
            manualSteps: $data['manual_steps'] ?? $data['manualSteps'] ?? [],
        );
    }

    /**
     * Calculate overall risk from individual risks.
     *
     * @param array<array{level: string, description: string, mitigation: ?string}> $risks
     * @param array<string> $prerequisites
     * @param array<string> $manualSteps
     */
    public static function calculate(
        array $risks,
        array $prerequisites = [],
        array $manualSteps = []
    ): self {
        $hasHigh = false;
        $mediumCount = 0;

        foreach ($risks as $risk) {
            $level = $risk['level'] ?? 'low';
            if ($level === 'high') {
                $hasHigh = true;
            } elseif ($level === 'medium') {
                $mediumCount++;
            }
        }

        $overall = match (true) {
            $hasHigh => 'high',
            $mediumCount >= 2 => 'high',
            $mediumCount >= 1 => 'medium',
            count($risks) > 0 => 'low',
            default => 'low',
        };

        return new self(
            overallLevel: $overall,
            risks: $risks,
            prerequisites: $prerequisites,
            requiresManualSteps: !empty($manualSteps),
            manualSteps: $manualSteps,
        );
    }

    /**
     * Check if any risks exist.
     */
    public function hasRisks(): bool
    {
        return !empty($this->risks);
    }

    /**
     * Get high-level risks only.
     *
     * @return array<array{level: string, description: string, mitigation: ?string}>
     */
    public function getHighRisks(): array
    {
        return array_filter($this->risks, fn($r) => ($r['level'] ?? 'low') === 'high');
    }

    /**
     * Get risk count by level.
     *
     * @return array{high: int, medium: int, low: int}
     */
    public function getRiskCounts(): array
    {
        $counts = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($this->risks as $risk) {
            $level = $risk['level'] ?? 'low';
            if (isset($counts[$level])) {
                $counts[$level]++;
            }
        }
        return $counts;
    }

    /**
     * Check if safe to auto-execute.
     */
    public function isSafeForAutoExecution(): bool
    {
        return $this->overallLevel === 'low'
            && !$this->requiresManualSteps
            && empty($this->prerequisites);
    }

    /**
     * Get a summary string.
     */
    public function getSummary(): string
    {
        $counts = $this->getRiskCounts();
        $parts = [];

        if ($counts['high'] > 0) {
            $parts[] = "{$counts['high']} high risk" . ($counts['high'] > 1 ? 's' : '');
        }
        if ($counts['medium'] > 0) {
            $parts[] = "{$counts['medium']} medium risk" . ($counts['medium'] > 1 ? 's' : '');
        }
        if ($counts['low'] > 0) {
            $parts[] = "{$counts['low']} low risk" . ($counts['low'] > 1 ? 's' : '');
        }

        if (empty($parts)) {
            return "No risks identified";
        }

        return "Overall: {$this->overallLevel} - " . implode(', ', $parts);
    }

    /**
     * Get badge color for UI.
     */
    public function getBadgeColor(): string
    {
        return match ($this->overallLevel) {
            'high' => 'red',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'overall_level' => $this->overallLevel,
            'risks' => $this->risks,
            'prerequisites' => $this->prerequisites,
            'requires_manual_steps' => $this->requiresManualSteps,
            'manual_steps' => $this->manualSteps,
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
