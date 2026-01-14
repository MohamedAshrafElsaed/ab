<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectScan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectScan>
 */
class ProjectScanFactory extends Factory
{
    protected $model = ProjectScan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'status' => 'pending',
            'current_stage' => null,
            'stage_percent' => 0,
            'commit_sha' => null,
            'trigger' => 'manual',
            'started_at' => null,
            'finished_at' => null,
            'last_error' => null,
            'meta' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'running',
            'current_stage' => 'manifest',
            'stage_percent' => 50,
            'started_at' => now()->subMinutes(2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
            'current_stage' => 'finalize',
            'stage_percent' => 100,
            'commit_sha' => fake()->sha1(),
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'failed',
            'current_stage' => 'clone',
            'stage_percent' => 15,
            'started_at' => now()->subMinutes(1),
            'finished_at' => now(),
            'last_error' => 'Failed to clone repository',
        ]);
    }

    public function webhook(): static
    {
        return $this->state(fn(array $attributes) => [
            'trigger' => 'webhook',
        ]);
    }
}
