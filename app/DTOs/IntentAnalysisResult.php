<?php

namespace App\DTOs;

use App\Enums\ComplexityLevel;
use App\Enums\IntentType;
use InvalidArgumentException;

readonly class IntentAnalysisResult
{
    /**
     * @param array{files?: array<string>, components?: array<string>, features?: array<string>, symbols?: array<string>} $extractedEntities
     * @param array{primary: string, secondary: array<string>} $domainClassification
     * @param array<string> $clarificationQuestions
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public IntentType $intentType,
        public float $confidenceScore,
        public array $extractedEntities,
        public array $domainClassification,
        public ComplexityLevel $complexityEstimate,
        public bool $requiresClarification,
        public array $clarificationQuestions,
        public array $metadata = [],
    ) {
        if ($this->confidenceScore < 0.0 || $this->confidenceScore > 1.0) {
            throw new InvalidArgumentException('Confidence score must be between 0 and 1');
        }
    }

    /**
     * Create from Claude API JSON response.
     *
     * @param array<string, mixed> $data
     */
    public static function fromClaudeResponse(array $data, array $metadata = []): self
    {
        $intentType = self::parseIntentType($data['intent_type'] ?? 'unknown');
        $complexityLevel = self::parseComplexityLevel($data['complexity_estimate'] ?? 'medium');

        return new self(
            intentType: $intentType,
            confidenceScore: self::normalizeConfidence($data['confidence_score'] ?? 0.5),
            extractedEntities: self::parseExtractedEntities($data['extracted_entities'] ?? []),
            domainClassification: self::parseDomainClassification($data['domain_classification'] ?? []),
            complexityEstimate: $complexityLevel,
            requiresClarification: (bool) ($data['requires_clarification'] ?? false),
            clarificationQuestions: self::parseClarificationQuestions($data['clarification_questions'] ?? []),
            metadata: $metadata,
        );
    }

    /**
     * Create a result indicating clarification is needed.
     *
     * @param array<string> $questions
     */
    public static function needsClarification(array $questions, string $primaryDomain = 'general'): self
    {
        return new self(
            intentType: IntentType::Unknown,
            confidenceScore: 0.3,
            extractedEntities: [],
            domainClassification: ['primary' => $primaryDomain, 'secondary' => []],
            complexityEstimate: ComplexityLevel::Medium,
            requiresClarification: true,
            clarificationQuestions: $questions,
            metadata: ['reason' => 'ambiguous_request'],
        );
    }

    /**
     * Create a result for a failed analysis.
     */
    public static function failed(string $reason): self
    {
        return new self(
            intentType: IntentType::Unknown,
            confidenceScore: 0.0,
            extractedEntities: [],
            domainClassification: ['primary' => 'unknown', 'secondary' => []],
            complexityEstimate: ComplexityLevel::Medium,
            requiresClarification: true,
            clarificationQuestions: ['Could you please rephrase your request?'],
            metadata: ['error' => $reason, 'failed' => true],
        );
    }

    private static function parseIntentType(string $value): IntentType
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'feature_request', 'feature', 'new_feature' => IntentType::FeatureRequest,
            'bug_fix', 'bug', 'fix', 'bugfix' => IntentType::BugFix,
            'test_writing', 'test', 'tests', 'testing' => IntentType::TestWriting,
            'ui_component', 'ui', 'component', 'frontend' => IntentType::UiComponent,
            'refactoring', 'refactor', 'cleanup' => IntentType::Refactoring,
            'question', 'query', 'ask' => IntentType::Question,
            'clarification', 'clarify' => IntentType::Clarification,
            default => IntentType::Unknown,
        };
    }

    private static function parseComplexityLevel(string $value): ComplexityLevel
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'trivial', 'tiny' => ComplexityLevel::Trivial,
            'simple', 'easy', 'small' => ComplexityLevel::Simple,
            'medium', 'moderate', 'normal' => ComplexityLevel::Medium,
            'complex', 'hard', 'difficult' => ComplexityLevel::Complex,
            'major', 'huge', 'large', 'massive' => ComplexityLevel::Major,
            default => ComplexityLevel::Medium,
        };
    }

    private static function normalizeConfidence(mixed $value): float
    {
        if (is_numeric($value)) {
            $score = (float) $value;

            if ($score > 1.0) {
                $score = $score / 100.0;
            }

            return max(0.0, min(1.0, $score));
        }

        return 0.5;
    }

    /**
     * @return array{files: array<string>, components: array<string>, features: array<string>, symbols: array<string>}
     */
    private static function parseExtractedEntities(mixed $data): array
    {
        if (!is_array($data)) {
            return ['files' => [], 'components' => [], 'features' => [], 'symbols' => []];
        }

        return [
            'files' => self::ensureStringArray($data['files'] ?? $data['mentioned_files'] ?? []),
            'components' => self::ensureStringArray($data['components'] ?? $data['mentioned_components'] ?? []),
            'features' => self::ensureStringArray($data['features'] ?? $data['mentioned_features'] ?? []),
            'symbols' => self::ensureStringArray($data['symbols'] ?? $data['mentioned_symbols'] ?? []),
        ];
    }

    /**
     * @return array{primary: string, secondary: array<string>}
     */
    private static function parseDomainClassification(mixed $data): array
    {
        if (!is_array($data)) {
            return ['primary' => 'general', 'secondary' => []];
        }

        return [
            'primary' => is_string($data['primary'] ?? null) ? $data['primary'] : 'general',
            'secondary' => self::ensureStringArray($data['secondary'] ?? []),
        ];
    }

    /**
     * @return array<string>
     */
    private static function parseClarificationQuestions(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        return self::ensureStringArray($data);
    }

    /**
     * @return array<string>
     */
    private static function ensureStringArray(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn($item) => is_string($item) ? trim($item) : null, $data),
            fn($item) => $item !== null && $item !== ''
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent_type' => $this->intentType->value,
            'confidence_score' => $this->confidenceScore,
            'extracted_entities' => $this->extractedEntities,
            'domain_classification' => $this->domainClassification,
            'complexity_estimate' => $this->complexityEstimate->value,
            'requires_clarification' => $this->requiresClarification,
            'clarification_questions' => $this->clarificationQuestions,
            'metadata' => $this->metadata,
        ];
    }

    public function isHighConfidence(float $threshold = 0.8): bool
    {
        return $this->confidenceScore >= $threshold;
    }

    public function isFailed(): bool
    {
        return ($this->metadata['failed'] ?? false) === true;
    }

    public function getPrimaryDomain(): string
    {
        return $this->domainClassification['primary'];
    }

    /**
     * @return array<string>
     */
    public function getSecondaryDomains(): array
    {
        return $this->domainClassification['secondary'];
    }

    /**
     * @return array<string>
     */
    public function getMentionedFiles(): array
    {
        return $this->extractedEntities['files'] ?? [];
    }

    /**
     * @return array<string>
     */
    public function getMentionedComponents(): array
    {
        return $this->extractedEntities['components'] ?? [];
    }
}
