<?php

namespace App\DTOs;

use JsonSerializable;

/**
 * Result of validating an execution plan.
 */
readonly class ValidationResult implements JsonSerializable
{
    /**
     * @param array<string> $errors
     * @param array<string> $warnings
     * @param array<string> $missingFiles
     * @param array<array{from: string, to: string, cycle: array<string>}> $circularDependencies
     */
    public function __construct(
        public bool $isValid,
        public array $errors,
        public array $warnings,
        public array $missingFiles,
        public array $circularDependencies,
    ) {}

    /**
     * Create a valid result with no issues.
     */
    public static function valid(): self
    {
        return new self(
            isValid: true,
            errors: [],
            warnings: [],
            missingFiles: [],
            circularDependencies: [],
        );
    }

    /**
     * Create a valid result with warnings.
     *
     * @param array<string> $warnings
     */
    public static function validWithWarnings(array $warnings): self
    {
        return new self(
            isValid: true,
            errors: [],
            warnings: $warnings,
            missingFiles: [],
            circularDependencies: [],
        );
    }

    /**
     * Create an invalid result.
     *
     * @param array<string> $errors
     * @param array<string> $warnings
     * @param array<string> $missingFiles
     * @param array<array{from: string, to: string, cycle: array<string>}> $circularDependencies
     */
    public static function invalid(
        array $errors,
        array $warnings = [],
        array $missingFiles = [],
        array $circularDependencies = []
    ): self {
        return new self(
            isValid: false,
            errors: $errors,
            warnings: $warnings,
            missingFiles: $missingFiles,
            circularDependencies: $circularDependencies,
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
            isValid: $data['is_valid'] ?? $data['isValid'] ?? false,
            errors: $data['errors'] ?? [],
            warnings: $data['warnings'] ?? [],
            missingFiles: $data['missing_files'] ?? $data['missingFiles'] ?? [],
            circularDependencies: $data['circular_dependencies'] ?? $data['circularDependencies'] ?? [],
        );
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Check if there are missing files.
     */
    public function hasMissingFiles(): bool
    {
        return !empty($this->missingFiles);
    }

    /**
     * Check if there are circular dependencies.
     */
    public function hasCircularDependencies(): bool
    {
        return !empty($this->circularDependencies);
    }

    /**
     * Get total issue count.
     */
    public function getTotalIssueCount(): int
    {
        return count($this->errors)
            + count($this->missingFiles)
            + count($this->circularDependencies);
    }

    /**
     * Get a summary string.
     */
    public function getSummary(): string
    {
        if ($this->isValid && !$this->hasWarnings()) {
            return "Plan is valid and ready for execution";
        }

        if ($this->isValid) {
            return "Plan is valid with " . count($this->warnings) . " warning(s)";
        }

        $issues = [];
        if (count($this->errors) > 0) {
            $issues[] = count($this->errors) . " error(s)";
        }
        if (count($this->missingFiles) > 0) {
            $issues[] = count($this->missingFiles) . " missing file(s)";
        }
        if (count($this->circularDependencies) > 0) {
            $issues[] = count($this->circularDependencies) . " circular dependency(s)";
        }

        return "Plan is invalid: " . implode(', ', $issues);
    }

    /**
     * Merge another validation result into this one.
     */
    public function merge(ValidationResult $other): self
    {
        return new self(
            isValid: $this->isValid && $other->isValid,
            errors: array_merge($this->errors, $other->errors),
            warnings: array_merge($this->warnings, $other->warnings),
            missingFiles: array_unique(array_merge($this->missingFiles, $other->missingFiles)),
            circularDependencies: array_merge($this->circularDependencies, $other->circularDependencies),
        );
    }

    /**
     * Add an error.
     */
    public function withError(string $error): self
    {
        return new self(
            isValid: false,
            errors: [...$this->errors, $error],
            warnings: $this->warnings,
            missingFiles: $this->missingFiles,
            circularDependencies: $this->circularDependencies,
        );
    }

    /**
     * Add a warning.
     */
    public function withWarning(string $warning): self
    {
        return new self(
            isValid: $this->isValid,
            errors: $this->errors,
            warnings: [...$this->warnings, $warning],
            missingFiles: $this->missingFiles,
            circularDependencies: $this->circularDependencies,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'missing_files' => $this->missingFiles,
            'circular_dependencies' => $this->circularDependencies,
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
