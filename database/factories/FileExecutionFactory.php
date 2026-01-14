<?php

namespace Database\Factories;

use App\Enums\FileExecutionStatus;
use App\Enums\FileOperationType;
use App\Models\ExecutionPlan;
use App\Models\FileExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileExecution>
 */
class FileExecutionFactory extends Factory
{
    protected $model = FileExecution::class;

    public function definition(): array
    {
        return [
            'execution_plan_id' => ExecutionPlan::factory(),
            'file_operation_index' => $this->faker->numberBetween(0, 10),
            'operation_type' => $this->faker->randomElement(FileOperationType::cases()),
            'file_path' => 'app/' . $this->faker->word() . '/' . ucfirst($this->faker->word()) . '.php',
            'status' => FileExecutionStatus::Pending,
            'user_approved' => false,
            'auto_approved' => false,
            'metadata' => [],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => FileExecutionStatus::Pending,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => FileExecutionStatus::InProgress,
            'execution_started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => FileExecutionStatus::Completed,
            'execution_started_at' => now()->subMinutes(2),
            'execution_completed_at' => now(),
            'new_content' => "<?php\n\nclass Test {}\n",
            'diff' => "--- a/file.php\n+++ b/file.php\n@@ -1,1 +1,3 @@\n+<?php\n+\n+class Test {}\n",
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => FileExecutionStatus::Failed,
            'execution_started_at' => now()->subMinutes(1),
            'execution_completed_at' => now(),
            'error_message' => 'File write failed: Permission denied',
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => FileExecutionStatus::Skipped,
            'metadata' => ['skip_reason' => 'User skipped'],
        ]);
    }

    public function rolledBack(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => FileExecutionStatus::RolledBack,
            'metadata' => ['rolled_back_at' => now()->toIso8601String()],
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn(array $attrs) => [
            'user_approved' => true,
        ]);
    }

    public function autoApproved(): static
    {
        return $this->state(fn(array $attrs) => [
            'auto_approved' => true,
        ]);
    }

    public function withOriginalContent(): static
    {
        return $this->state(fn(array $attrs) => [
            'original_content' => "<?php\n\n// Original content\n",
        ]);
    }

    public function withBackup(): static
    {
        return $this->state(fn(array $attrs) => [
            'backup_path' => storage_path('app/backups/test_backup.php'),
            'original_content' => "<?php\n\n// Original\n",
        ]);
    }

    public function createOperation(): static
    {
        return $this->state(fn(array $attrs) => [
            'operation_type' => FileOperationType::Create,
        ]);
    }

    public function modifyOperation(): static
    {
        return $this->state(fn(array $attrs) => [
            'operation_type' => FileOperationType::Modify,
        ]);
    }

    public function deleteOperation(): static
    {
        return $this->state(fn(array $attrs) => [
            'operation_type' => FileOperationType::Delete,
        ]);
    }

    public function rollbackable(): static
    {
        return $this->completed()->withOriginalContent();
    }
}
