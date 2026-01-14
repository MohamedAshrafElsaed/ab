<?php

namespace Database\Factories;

use App\Enums\ComplexityLevel;
use App\Enums\FileOperationType;
use App\Enums\PlanStatus;
use App\Models\ExecutionPlan;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExecutionPlan>
 */
class ExecutionPlanFactory extends Factory
{
    protected $model = ExecutionPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileCount = $this->faker->numberBetween(1, 5);

        return [
            'id' => (string) Str::uuid(),
            'project_id' => Project::factory(),
            'conversation_id' => (string) Str::uuid(),
            'intent_analysis_id' => IntentAnalysis::factory(),
            'status' => PlanStatus::Draft,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(2),
            'plan_data' => [
                'approach' => $this->faker->paragraph(),
                'testing_notes' => 'Run: php artisan test',
                'estimated_time' => '15-30 minutes',
            ],
            'file_operations' => $this->generateFileOperations($fileCount),
            'estimated_complexity' => $this->faker->randomElement(ComplexityLevel::values()),
            'estimated_files_affected' => $fileCount,
            'risks' => $this->generateRisks(),
            'prerequisites' => $this->faker->randomElements([
                'Database migrations must be run',
                'Environment variables configured',
                'Composer packages installed',
            ], $this->faker->numberBetween(0, 2)),
            'metadata' => [
                'generation_time_ms' => $this->faker->randomFloat(2, 500, 5000),
                'model' => 'claude-sonnet-4-5-20250514',
            ],
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function generateFileOperations(int $count): array
    {
        $operations = [];
        $paths = [
            'app/Http/Controllers/TestController.php',
            'app/Services/TestService.php',
            'app/Models/TestModel.php',
            'routes/web.php',
            'resources/views/test.blade.php',
            'database/migrations/create_tests_table.php',
        ];

        for ($i = 0; $i < $count; $i++) {
            $type = $this->faker->randomElement(FileOperationType::values());
            $path = $paths[$i % count($paths)];

            $operation = [
                'type' => $type,
                'path' => $path,
                'priority' => $i + 1,
                'description' => $this->faker->sentence(),
                'dependencies' => [],
            ];

            if ($type === 'create') {
                $operation['template_content'] = "<?php\n\n// Generated file content\nclass Test {}\n";
            } elseif ($type === 'modify') {
                $operation['changes'] = [
                    [
                        'section' => 'methods',
                        'change_type' => 'add',
                        'before' => null,
                        'after' => "public function test() { return true; }",
                        'start_line' => 10,
                        'end_line' => 10,
                        'explanation' => 'Adding test method',
                    ],
                ];
            } elseif (in_array($type, ['rename', 'move'])) {
                $operation['new_path'] = str_replace('Test', 'NewTest', $path);
            }

            $operations[] = $operation;
        }

        return $operations;
    }

    /**
     * @return array<array{level: string, description: string, mitigation: string|null}>
     */
    private function generateRisks(): array
    {
        $riskCount = $this->faker->numberBetween(0, 3);
        $risks = [];

        for ($i = 0; $i < $riskCount; $i++) {
            $risks[] = [
                'level' => $this->faker->randomElement(['low', 'medium', 'high']),
                'description' => $this->faker->sentence(),
                'mitigation' => $this->faker->boolean(70) ? $this->faker->sentence() : null,
            ];
        }

        return $risks;
    }

    public function pendingReview(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PlanStatus::PendingReview,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PlanStatus::Approved,
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PlanStatus::Rejected,
            'user_feedback' => 'Rejected because: ' . $this->faker->sentence(),
        ]);
    }

    public function executing(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PlanStatus::Executing,
            'approved_at' => now()->subMinutes(5),
            'approved_by' => User::factory(),
            'execution_started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PlanStatus::Completed,
            'approved_at' => now()->subMinutes(10),
            'approved_by' => User::factory(),
            'execution_started_at' => now()->subMinutes(5),
            'execution_completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PlanStatus::Failed,
            'approved_at' => now()->subMinutes(10),
            'execution_started_at' => now()->subMinutes(5),
            'execution_completed_at' => now(),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'failure_reason' => 'Test failure: ' . $this->faker->sentence(),
            ]),
        ]);
    }

    public function simple(): static
    {
        return $this->state(fn(array $attributes) => [
            'estimated_complexity' => ComplexityLevel::Simple,
            'estimated_files_affected' => 1,
            'file_operations' => [[
                'type' => 'modify',
                'path' => 'routes/web.php',
                'priority' => 1,
                'description' => 'Add new route',
                'changes' => [[
                    'section' => 'routes',
                    'change_type' => 'add',
                    'before' => null,
                    'after' => "Route::get('/test', fn() => 'test');",
                    'start_line' => 20,
                    'end_line' => 20,
                    'explanation' => 'Adding test route',
                ]],
                'dependencies' => [],
            ]],
            'risks' => [],
        ]);
    }

    public function complex(): static
    {
        return $this->state(fn(array $attributes) => [
            'estimated_complexity' => ComplexityLevel::Complex,
            'estimated_files_affected' => 10,
            'file_operations' => $this->generateFileOperations(10),
            'risks' => [
                ['level' => 'high', 'description' => 'Breaking change', 'mitigation' => 'Run full test suite'],
                ['level' => 'medium', 'description' => 'Database changes', 'mitigation' => 'Backup first'],
            ],
        ]);
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn(array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    public function forConversation(string $conversationId): static
    {
        return $this->state(fn(array $attributes) => [
            'conversation_id' => $conversationId,
        ]);
    }

    public function withIntent(IntentAnalysis $intent): static
    {
        return $this->state(fn(array $attributes) => [
            'intent_analysis_id' => $intent->id,
            'project_id' => $intent->project_id,
        ]);
    }
}
