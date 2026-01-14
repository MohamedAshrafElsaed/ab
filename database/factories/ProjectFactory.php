<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'github',
            'repo_full_name' => fake()->userName() . '/' . fake()->slug(2),
            'repo_id' => (string) fake()->randomNumber(8),
            'default_branch' => 'main',
            'status' => 'pending',
            'current_stage' => null,
            'stage_percent' => 0,
            'scanned_at' => null,
            'last_commit_sha' => null,
            'last_error' => null,
            'stack_info' => null,
            'total_files' => 0,
            'total_lines' => 0,
            'total_size_bytes' => 0,
        ];
    }

    public function scanning(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'scanning',
            'current_stage' => 'manifest',
            'stage_percent' => 35,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'ready',
            'current_stage' => null,
            'stage_percent' => 100,
            'scanned_at' => now(),
            'last_commit_sha' => fake()->sha1(),
            'total_files' => fake()->numberBetween(50, 500),
            'total_lines' => fake()->numberBetween(5000, 50000),
            'total_size_bytes' => fake()->numberBetween(100000, 5000000),
            'stack_info' => [
                'framework' => 'laravel',
                'framework_version' => '11.x',
                'php_version' => '^8.2',
                'frontend' => ['vue', 'inertia'],
                'css' => ['tailwind'],
                'build_tools' => ['vite'],
                'testing' => ['phpunit'],
                'database' => ['mysql'],
                'features' => ['blade', 'api', 'queues'],
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'failed',
            'current_stage' => 'clone',
            'stage_percent' => 15,
            'last_error' => 'Failed to clone repository: Authentication required',
        ]);
    }

    public function processing(): static
    {
        return $this->scanning();
    }

    public function withLaravelStack(): static
    {
        return $this->state(fn(array $attributes) => [
            'stack_info' => [
                'framework' => 'laravel',
                'framework_version' => '11.x',
                'php_version' => '^8.2',
                'frontend' => ['blade'],
                'css' => ['tailwind'],
                'build_tools' => ['vite'],
                'testing' => ['phpunit'],
                'database' => ['mysql'],
                'features' => ['blade', 'queues'],
            ],
        ]);
    }

    public function withVueStack(): static
    {
        return $this->state(fn(array $attributes) => [
            'stack_info' => [
                'framework' => 'laravel',
                'framework_version' => '11.x',
                'php_version' => '^8.2',
                'frontend' => ['vue', 'inertia', 'typescript'],
                'css' => ['tailwind'],
                'build_tools' => ['vite'],
                'testing' => ['phpunit', 'vitest'],
                'database' => ['mysql'],
                'features' => ['api', 'sanctum'],
            ],
        ]);
    }
}
