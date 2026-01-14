<?php

namespace Database\Factories;

use App\Enums\ComplexityLevel;
use App\Enums\IntentType;
use App\Models\IntentAnalysis;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IntentAnalysis>
 */
class IntentAnalysisFactory extends Factory
{
    protected $model = IntentAnalysis::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'project_id' => Project::factory(),
            'conversation_id' => (string) Str::uuid(),
            'message_id' => (string) Str::uuid(),
            'raw_input' => $this->faker->sentence(10),
            'intent_type' => $this->faker->randomElement(IntentType::values()),
            'confidence_score' => $this->faker->randomFloat(2, 0.3, 1.0),
            'extracted_entities' => [
                'files' => $this->faker->randomElements([
                    'UserController.php',
                    'AuthService.php',
                    'Dashboard.vue',
                    'api.php',
                ], $this->faker->numberBetween(0, 3)),
                'components' => $this->faker->randomElements([
                    'login form',
                    'navbar',
                    'user table',
                    'modal',
                ], $this->faker->numberBetween(0, 2)),
                'features' => $this->faker->randomElements([
                    'authentication',
                    'user management',
                    'export',
                    'notifications',
                ], $this->faker->numberBetween(0, 2)),
                'symbols' => [],
            ],
            'domain_classification' => [
                'primary' => $this->faker->randomElement([
                    'auth',
                    'users',
                    'api',
                    'ui',
                    'database',
                    'testing',
                ]),
                'secondary' => $this->faker->randomElements([
                    'services',
                    'config',
                    'middleware',
                ], $this->faker->numberBetween(0, 2)),
            ],
            'complexity_estimate' => $this->faker->randomElement(ComplexityLevel::values()),
            'requires_clarification' => $this->faker->boolean(20),
            'clarification_questions' => [],
            'metadata' => [
                'processing_time_ms' => $this->faker->randomFloat(2, 100, 2000),
                'tokens_used' => $this->faker->numberBetween(100, 500),
            ],
        ];
    }

    public function featureRequest(): static
    {
        return $this->state(fn(array $attributes) => [
            'intent_type' => IntentType::FeatureRequest->value,
            'raw_input' => 'Add a new ' . $this->faker->word() . ' feature',
        ]);
    }

    public function bugFix(): static
    {
        return $this->state(fn(array $attributes) => [
            'intent_type' => IntentType::BugFix->value,
            'raw_input' => 'Fix the ' . $this->faker->word() . ' bug',
        ]);
    }

    public function testWriting(): static
    {
        return $this->state(fn(array $attributes) => [
            'intent_type' => IntentType::TestWriting->value,
            'raw_input' => 'Write tests for ' . $this->faker->word(),
            'domain_classification' => [
                'primary' => 'testing',
                'secondary' => ['services'],
            ],
        ]);
    }

    public function question(): static
    {
        return $this->state(fn(array $attributes) => [
            'intent_type' => IntentType::Question->value,
            'raw_input' => 'How does ' . $this->faker->word() . ' work?',
            'complexity_estimate' => ComplexityLevel::Trivial->value,
        ]);
    }

    public function highConfidence(): static
    {
        return $this->state(fn(array $attributes) => [
            'confidence_score' => $this->faker->randomFloat(2, 0.85, 1.0),
            'requires_clarification' => false,
        ]);
    }

    public function lowConfidence(): static
    {
        return $this->state(fn(array $attributes) => [
            'confidence_score' => $this->faker->randomFloat(2, 0.1, 0.4),
            'requires_clarification' => true,
            'clarification_questions' => [
                'Could you provide more details?',
                'Which component are you referring to?',
            ],
        ]);
    }

    public function needingClarification(): static
    {
        return $this->state(fn(array $attributes) => [
            'intent_type' => IntentType::Unknown->value,
            'confidence_score' => $this->faker->randomFloat(2, 0.2, 0.4),
            'requires_clarification' => true,
            'clarification_questions' => [
                'What specifically would you like me to do?',
                'Could you provide more context?',
            ],
        ]);
    }

    public function trivialComplexity(): static
    {
        return $this->state(fn(array $attributes) => [
            'complexity_estimate' => ComplexityLevel::Trivial->value,
        ]);
    }

    public function majorComplexity(): static
    {
        return $this->state(fn(array $attributes) => [
            'complexity_estimate' => ComplexityLevel::Major->value,
        ]);
    }

    public function forConversation(string $conversationId): static
    {
        return $this->state(fn(array $attributes) => [
            'conversation_id' => $conversationId,
        ]);
    }

    public function inDomain(string $domain): static
    {
        return $this->state(fn(array $attributes) => [
            'domain_classification' => [
                'primary' => $domain,
                'secondary' => $attributes['domain_classification']['secondary'] ?? [],
            ],
        ]);
    }
}
