<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectFileChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFileChunk>
 */
class ProjectFileChunkFactory extends Factory
{
    protected $model = ProjectFileChunk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startLine = fake()->numberBetween(1, 100);
        $endLine = $startLine + fake()->numberBetween(50, 200);

        return [
            'project_id' => Project::factory(),
            'chunk_id' => sprintf('chunk_%04d', fake()->numberBetween(0, 100)),
            'path' => 'app/' . fake()->lexify('????') . '.php',
            'start_line' => $startLine,
            'end_line' => $endLine,
            'sha1' => fake()->sha1(),
            'chunk_file_path' => null,
            'chunk_size_bytes' => fake()->numberBetween(1000, 50000),
        ];
    }
}
