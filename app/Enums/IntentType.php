<?php

namespace App\Enums;

enum IntentType: string
{
    case FeatureRequest = 'feature_request';
    case BugFix = 'bug_fix';
    case TestWriting = 'test_writing';
    case UiComponent = 'ui_component';
    case Refactoring = 'refactoring';
    case Question = 'question';
    case Clarification = 'clarification';
    case Unknown = 'unknown';

    /**
     * Get a human-readable label for the intent type.
     */
    public function label(): string
    {
        return match ($this) {
            self::FeatureRequest => 'Feature Request',
            self::BugFix => 'Bug Fix',
            self::TestWriting => 'Test Writing',
            self::UiComponent => 'UI Component',
            self::Refactoring => 'Refactoring',
            self::Question => 'Question',
            self::Clarification => 'Clarification',
            self::Unknown => 'Unknown',
        };
    }

    /**
     * Get a description of what this intent type represents.
     */
    public function description(): string
    {
        return match ($this) {
            self::FeatureRequest => 'User wants to add new functionality to the codebase',
            self::BugFix => 'User wants to fix an existing bug or issue',
            self::TestWriting => 'User wants to create or update tests',
            self::UiComponent => 'User wants to create or modify UI components',
            self::Refactoring => 'User wants to improve code structure without changing behavior',
            self::Question => 'User is asking a question about the codebase',
            self::Clarification => 'User is providing clarification to a previous question',
            self::Unknown => 'Intent could not be determined',
        };
    }

    /**
     * Check if this intent type requires code changes.
     */
    public function requiresCodeChanges(): bool
    {
        return match ($this) {
            self::FeatureRequest,
            self::BugFix,
            self::TestWriting,
            self::UiComponent,
            self::Refactoring => true,
            self::Question,
            self::Clarification,
            self::Unknown => false,
        };
    }

    /**
     * Get the default complexity for this intent type.
     */
    public function defaultComplexity(): ComplexityLevel
    {
        return match ($this) {
            self::FeatureRequest => ComplexityLevel::Medium,
            self::BugFix => ComplexityLevel::Simple,
            self::TestWriting => ComplexityLevel::Simple,
            self::UiComponent => ComplexityLevel::Medium,
            self::Refactoring => ComplexityLevel::Complex,
            self::Question => ComplexityLevel::Trivial,
            self::Clarification => ComplexityLevel::Trivial,
            self::Unknown => ComplexityLevel::Medium,
        };
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
}
