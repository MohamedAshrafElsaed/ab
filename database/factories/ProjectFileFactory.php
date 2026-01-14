<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFile>
 */
class ProjectFileFactory extends Factory
{
    protected $model = ProjectFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extensions = ['php', 'js', 'vue', 'ts', 'css', 'json', 'md', 'blade.php'];
        $extension = fake()->randomElement($extensions);
        $directories = ['app', 'resources', 'routes', 'config', 'database', 'tests'];
        $directory = fake()->randomElement($directories);

        return [
            'project_id' => Project::factory(),
            'path' => $directory . '/' . fake()->lexify('????') . '.' . $extension,
            'extension' => $extension,
            'size_bytes' => fake()->numberBetween(100, 50000),
            'sha1' => fake()->sha1(),
            'line_count' => fake()->numberBetween(10, 500),
            'is_binary' => false,
            'mime_type' => 'text/plain',
            'file_modified_at' => fake()->dateTimeThisMonth(),
        ];
    }

    public function binary(): static
    {
        return $this->state(fn(array $attributes) => [
            'extension' => fake()->randomElement(['png', 'jpg', 'pdf']),
            'is_binary' => true,
            'line_count' => 0,
            'mime_type' => 'application/octet-stream',
        ]);
    }

    public function php(): static
    {
        return $this->state(fn(array $attributes) => [
            'path' => 'app/' . fake()->lexify('????') . '.php',
            'extension' => 'php',
            'mime_type' => 'text/x-php',
        ]);
    }

    public function vue(): static
    {
        return $this->state(fn(array $attributes) => [
            'path' => 'resources/js/components/' . fake()->lexify('????') . '.vue',
            'extension' => 'vue',
            'mime_type' => 'text/plain',
        ]);
    }

    public function blade(): static
    {
        return $this->state(fn(array $attributes) => [
            'path' => 'resources/views/' . fake()->lexify('????') . '.blade.php',
            'extension' => 'blade.php',
            'mime_type' => 'text/x-php',
        ]);
    }
}
