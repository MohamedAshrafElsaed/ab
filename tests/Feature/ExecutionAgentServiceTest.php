<?php

namespace Tests\Feature;

use App\DTOs\FileExecutionResult;
use App\DTOs\FileOperation;
use App\DTOs\ModificationResult;
use App\DTOs\PlannedChange;
use App\DTOs\RollbackResult;
use App\DTOs\WriteResult;
use App\Enums\FileExecutionStatus;
use App\Enums\FileOperationType;
use App\Enums\PlanStatus;
use App\Events\ExecutionEvent;
use App\Models\ExecutionPlan;
use App\Models\FileExecution;
use App\Models\Project;
use App\Models\User;
use App\Services\AI\ExecutionAgentService;
use App\Services\Files\DiffGeneratorService;
use App\Services\Files\FileWriterService;
use App\Services\Prompts\PromptTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExecutionAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExecutionAgentService $service;
    private DiffGeneratorService $diffService;
    private FileWriterService $fileWriter;
    private Project $project;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'stack_info' => [
                'framework' => 'laravel',
                'frontend' => ['vue', 'inertia'],
            ],
        ]);

        // Create directories at the project's actual repo_path
        $repoPath = $this->project->repo_path;
        File::makeDirectory($repoPath, 0755, true, true);
        File::makeDirectory($repoPath . '/app', 0755, true, true);
        File::makeDirectory($repoPath . '/app/Services', 0755, true, true);

        $this->diffService = new DiffGeneratorService();
        $this->fileWriter = new FileWriterService();
        $this->service = app(ExecutionAgentService::class);
    }

    protected function tearDown(): void
    {
        // Clean up project storage directory
        $storagePath = $this->project->storage_path;
        if (is_dir($storagePath)) {
            File::deleteDirectory($storagePath);
        }

        parent::tearDown();
    }

    // =========================================================================
    // FileExecutionStatus Enum Tests
    // =========================================================================

    public function test_file_execution_status_properties(): void
    {
        $status = FileExecutionStatus::Completed;

        $this->assertEquals('Completed', $status->label());
        $this->assertEquals('green', $status->color());
        $this->assertEquals('check-circle', $status->icon());
        $this->assertTrue($status->isTerminal());
        $this->assertTrue($status->canRollback());
    }

    public function test_file_execution_status_terminal_states(): void
    {
        $this->assertTrue(FileExecutionStatus::Completed->isTerminal());
        $this->assertTrue(FileExecutionStatus::Failed->isTerminal());
        $this->assertTrue(FileExecutionStatus::Skipped->isTerminal());
        $this->assertTrue(FileExecutionStatus::RolledBack->isTerminal());

        $this->assertFalse(FileExecutionStatus::Pending->isTerminal());
        $this->assertFalse(FileExecutionStatus::InProgress->isTerminal());
    }

    public function test_file_execution_status_can_retry(): void
    {
        $this->assertTrue(FileExecutionStatus::Failed->canRetry());
        $this->assertTrue(FileExecutionStatus::RolledBack->canRetry());
        $this->assertFalse(FileExecutionStatus::Completed->canRetry());
        $this->assertFalse(FileExecutionStatus::Pending->canRetry());
    }

    public function test_file_execution_status_values(): void
    {
        $values = FileExecutionStatus::values();

        $this->assertContains('pending', $values);
        $this->assertContains('in_progress', $values);
        $this->assertContains('completed', $values);
        $this->assertContains('failed', $values);
        $this->assertContains('skipped', $values);
        $this->assertContains('rolled_back', $values);
        $this->assertCount(6, $values);
    }

    // =========================================================================
    // FileExecution Model Tests
    // =========================================================================

    public function test_file_execution_status_transitions(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
        ]);

        $execution = FileExecution::factory()->pending()->create([
            'execution_plan_id' => $plan->id,
        ]);

        $execution->markInProgress();
        $this->assertEquals(FileExecutionStatus::InProgress, $execution->fresh()->status);
        $this->assertNotNull($execution->fresh()->execution_started_at);

        $execution->markCompleted('new content', 'diff here');
        $this->assertEquals(FileExecutionStatus::Completed, $execution->fresh()->status);
        $this->assertEquals('new content', $execution->fresh()->new_content);
        $this->assertNotNull($execution->fresh()->execution_completed_at);
    }

    public function test_file_execution_mark_failed(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
        ]);

        $execution = FileExecution::factory()->inProgress()->create([
            'execution_plan_id' => $plan->id,
        ]);

        $execution->markFailed('Something went wrong');

        $this->assertEquals(FileExecutionStatus::Failed, $execution->fresh()->status);
        $this->assertEquals('Something went wrong', $execution->fresh()->error_message);
    }

    public function test_file_execution_approval(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
        ]);

        $execution = FileExecution::factory()->pending()->create([
            'execution_plan_id' => $plan->id,
        ]);

        $this->assertFalse($execution->is_approved);

        $execution->approve();
        $this->assertTrue($execution->fresh()->is_approved);
        $this->assertTrue($execution->fresh()->user_approved);
    }

    public function test_file_execution_auto_approval(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
        ]);

        $execution = FileExecution::factory()->pending()->create([
            'execution_plan_id' => $plan->id,
        ]);

        $execution->autoApprove();
        $this->assertTrue($execution->fresh()->is_approved);
        $this->assertTrue($execution->fresh()->auto_approved);
    }

    public function test_file_execution_can_rollback(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
        ]);

        $execution = FileExecution::factory()
            ->completed()
            ->withOriginalContent()
            ->create(['execution_plan_id' => $plan->id]);

        $this->assertTrue($execution->can_rollback);

        $executionWithoutOriginal = FileExecution::factory()->completed()->create([
            'execution_plan_id' => $plan->id,
            'original_content' => null,
        ]);
        $this->assertFalse($executionWithoutOriginal->can_rollback);
    }

    public function test_file_execution_diff_lines_accessor(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
        ]);

        $diff = "--- a/file.php\n+++ b/file.php\n@@ -1,2 +1,3 @@\n context\n-removed\n+added";
        $execution = FileExecution::factory()->create([
            'execution_plan_id' => $plan->id,
            'diff' => $diff,
        ]);

        $lines = $execution->diff_lines;

        $this->assertCount(6, $lines);
        $this->assertEquals('removed', $lines[4]['type']);
        $this->assertEquals('added', $lines[5]['type']);
    }

    public function test_file_execution_scopes(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
        ]);

        FileExecution::factory()->pending()->count(3)->create([
            'execution_plan_id' => $plan->id,
        ]);
        FileExecution::factory()->completed()->count(2)->create([
            'execution_plan_id' => $plan->id,
        ]);
        FileExecution::factory()->failed()->count(1)->create([
            'execution_plan_id' => $plan->id,
        ]);

        $this->assertEquals(3, FileExecution::pending()->count());
        $this->assertEquals(2, FileExecution::completed()->count());
        $this->assertEquals(1, FileExecution::failed()->count());
    }

    // =========================================================================
    // DiffGeneratorService Tests
    // =========================================================================

    public function test_diff_generator_creates_unified_diff(): void
    {
        $original = "line 1\nline 2\nline 3";
        $modified = "line 1\nmodified line\nline 3";

        $diff = $this->diffService->generateDiff($original, $modified, 'test.txt');

        $this->assertStringContainsString('--- a/test.txt', $diff);
        $this->assertStringContainsString('+++ b/test.txt', $diff);
        $this->assertStringContainsString('-line 2', $diff);
        $this->assertStringContainsString('+modified line', $diff);
    }

    public function test_diff_generator_parses_diff(): void
    {
        $diff = "--- a/file.php\n+++ b/file.php\n@@ -1,2 +1,3 @@\n context\n-removed\n+added";

        $changes = $this->diffService->parseDiff($diff);

        $added = array_filter($changes, fn($c) => $c['type'] === 'added');
        $removed = array_filter($changes, fn($c) => $c['type'] === 'removed');

        $this->assertCount(1, $added);
        $this->assertCount(1, $removed);
    }

    public function test_diff_generator_stats(): void
    {
        $original = "line 1\nline 2\nline 3";
        $modified = "line 1\nnew line\nline 3\nanother new";

        $diff = $this->diffService->generateDiff($original, $modified, 'test.txt');
        $stats = $this->diffService->getStats($diff);

        $this->assertArrayHasKey('added', $stats);
        $this->assertArrayHasKey('removed', $stats);
        $this->assertEquals(2, $stats['added']);
        $this->assertEquals(1, $stats['removed']);
    }

    public function test_diff_generator_empty_to_content(): void
    {
        $diff = $this->diffService->generateDiff('', 'new content', 'file.txt');

        $this->assertStringContainsString('+new content', $diff);
    }

    public function test_diff_generator_content_to_empty(): void
    {
        $diff = $this->diffService->generateDiff('old content', '', 'file.txt');

        $this->assertStringContainsString('-old content', $diff);
    }

    // =========================================================================
    // FileWriterService Tests
    // =========================================================================

    public function test_file_writer_creates_file(): void
    {
        $result = $this->fileWriter->createFile($this->project, 'app/NewFile.php', '<?php class NewFile {}');

        $this->assertTrue($result->success);
        $this->assertFileExists($this->project->repo_path . '/app/NewFile.php');
        $this->assertEquals('<?php class NewFile {}', file_get_contents($this->project->repo_path . '/app/NewFile.php'));
    }

    public function test_file_writer_fails_on_existing_file(): void
    {
        file_put_contents($this->project->repo_path . '/app/Existing.php', 'original');

        $result = $this->fileWriter->createFile($this->project, 'app/Existing.php', 'new content');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('already exists', $result->error);
    }

    public function test_file_writer_modifies_file(): void
    {
        file_put_contents($this->project->repo_path . '/app/ToModify.php', 'original content');

        $result = $this->fileWriter->modifyFile($this->project, 'app/ToModify.php', 'modified content');

        $this->assertTrue($result->success);
        $this->assertEquals('modified content', file_get_contents($this->project->repo_path . '/app/ToModify.php'));
        $this->assertEquals('original content', $result->originalContent);
        $this->assertNotNull($result->backupPath);
    }

    public function test_file_writer_deletes_file(): void
    {
        file_put_contents($this->project->repo_path . '/app/ToDelete.php', 'to be deleted');

        $result = $this->fileWriter->deleteFile($this->project, 'app/ToDelete.php');

        $this->assertTrue($result->success);
        $this->assertFileDoesNotExist($this->project->repo_path . '/app/ToDelete.php');
        $this->assertNotNull($result->backupPath);
    }

    public function test_file_writer_moves_file(): void
    {
        file_put_contents($this->project->repo_path . '/app/OldName.php', 'file content');

        $result = $this->fileWriter->moveFile($this->project, 'app/OldName.php', 'app/NewName.php');

        $this->assertTrue($result->success);
        $this->assertFileDoesNotExist($this->project->repo_path . '/app/OldName.php');
        $this->assertFileExists($this->project->repo_path . '/app/NewName.php');
    }

    public function test_file_writer_creates_nested_directories(): void
    {
        $result = $this->fileWriter->createFile(
            $this->project,
            'app/Services/Deep/Nested/Service.php',
            '<?php class Service {}'
        );

        $this->assertTrue($result->success);
        $this->assertFileExists($this->project->repo_path . '/app/Services/Deep/Nested/Service.php');
    }

    public function test_file_writer_restore_from_backup(): void
    {
        file_put_contents($this->project->repo_path . '/app/Original.php', 'original');
        $result = $this->fileWriter->modifyFile($this->project, 'app/Original.php', 'modified');

        $this->assertTrue($result->success);

        $restored = $this->fileWriter->restoreFromBackup(
            $result->backupPath,
            $this->project->repo_path . '/app/Original.php'
        );

        $this->assertTrue($restored);
        $this->assertEquals('original', file_get_contents($this->project->repo_path . '/app/Original.php'));
    }

    public function test_file_writer_read_file(): void
    {
        $content = '<?php class Test {}';
        file_put_contents($this->project->repo_path . '/app/ReadTest.php', $content);

        $result = $this->fileWriter->readFile($this->project, 'app/ReadTest.php');

        $this->assertEquals($content, $result);
    }

    public function test_file_writer_read_nonexistent_file(): void
    {
        $result = $this->fileWriter->readFile($this->project, 'app/NonExistent.php');

        $this->assertNull($result);
    }

    public function test_file_writer_file_exists(): void
    {
        file_put_contents($this->project->repo_path . '/app/Exists.php', 'content');

        $this->assertTrue($this->fileWriter->fileExists($this->project, 'app/Exists.php'));
        $this->assertFalse($this->fileWriter->fileExists($this->project, 'app/NotExists.php'));
    }

    // =========================================================================
    // DTO Tests
    // =========================================================================

    public function test_write_result_success(): void
    {
        $result = WriteResult::success('app/File.php', '/backup/path', 'original', ['key' => 'value']);

        $this->assertTrue($result->success);
        $this->assertEquals('app/File.php', $result->path);
        $this->assertEquals('/backup/path', $result->backupPath);
        $this->assertEquals('original', $result->originalContent);
    }

    public function test_write_result_failure(): void
    {
        $result = WriteResult::failure('app/File.php', 'Permission denied');

        $this->assertFalse($result->success);
        $this->assertEquals('Permission denied', $result->error);
    }

    public function test_modification_result_success(): void
    {
        $result = ModificationResult::success(
            'modified content',
            [['section' => 'methods', 'type' => 'add', 'applied' => true, 'explanation' => 'Added method']],
            '--- a/file\n+++ b/file'
        );

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->getTotalChanges());
        $this->assertEquals(1, $result->getAppliedCount());
        $this->assertTrue($result->allChangesApplied());
    }

    public function test_file_execution_result_success(): void
    {
        $result = FileExecutionResult::success('content', 'diff', '/backup');

        $this->assertTrue($result->success);
        $this->assertTrue($result->hasBackup());
        $this->assertTrue($result->hasDiff());
    }

    public function test_file_execution_result_skipped(): void
    {
        $result = FileExecutionResult::skipped('User requested skip');

        $this->assertTrue($result->success);
        $this->assertTrue($result->wasSkipped());
        $this->assertEquals('User requested skip', $result->metadata['skip_reason']);
    }

    public function test_rollback_result_from_results(): void
    {
        $result = RollbackResult::fromResults(
            ['file1.php', 'file2.php'],
            [['path' => 'file3.php', 'error' => 'Failed']],
            ['file4.php']
        );

        $this->assertFalse($result->success);
        $this->assertEquals(2, $result->getRolledBackCount());
        $this->assertEquals(1, $result->getFailedCount());
        $this->assertEquals(1, $result->getSkippedCount());
        $this->assertTrue($result->isPartialSuccess());
    }

    public function test_rollback_result_empty(): void
    {
        $result = RollbackResult::empty();

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->getTotalAttempted());
        $this->assertEquals('No changes to rollback', $result->getSummary());
    }

    // =========================================================================
    // ExecutionEvent Tests
    // =========================================================================

    public function test_execution_event_started(): void
    {
        $event = ExecutionEvent::started('plan-123', 5);

        $this->assertEquals('started', $event->type);
        $this->assertEquals('plan-123', $event->data['plan_id']);
        $this->assertEquals(5, $event->data['total_files']);
    }

    public function test_execution_event_file_completed(): void
    {
        $event = ExecutionEvent::fileCompleted('plan-123', 0, 'app/File.php', 'diff content');

        $this->assertEquals('file_completed', $event->type);
        $this->assertEquals('app/File.php', $event->data['path']);
        $this->assertEquals('diff content', $event->data['diff']);
        $this->assertTrue($event->data['success']);
    }

    public function test_execution_event_file_failed(): void
    {
        $event = ExecutionEvent::fileFailed('plan-123', 0, 'app/File.php', 'Permission denied');

        $this->assertEquals('file_failed', $event->type);
        $this->assertEquals('Permission denied', $event->data['error']);
        $this->assertFalse($event->data['success']);
    }

    public function test_execution_event_awaiting_approval(): void
    {
        $event = ExecutionEvent::awaitingApproval('plan-123', 'exec-456', 'app/File.php', 'diff');

        $this->assertEquals('awaiting_approval', $event->type);
        $this->assertEquals('exec-456', $event->data['execution_id']);
        $this->assertEquals('diff', $event->data['diff']);
    }

    public function test_execution_event_completed(): void
    {
        $event = ExecutionEvent::completed('plan-123', 5, 1);

        $this->assertEquals('completed', $event->type);
        $this->assertEquals(5, $event->data['files_completed']);
        $this->assertEquals(1, $event->data['files_failed']);
        $this->assertFalse($event->data['success']);
    }

    public function test_execution_event_rollback(): void
    {
        $event = ExecutionEvent::rollbackCompleted('plan-123', 3, 0);

        $this->assertEquals('rollback_completed', $event->type);
        $this->assertEquals(3, $event->data['rolled_back']);
        $this->assertTrue($event->data['success']);
    }

    public function test_execution_event_to_array(): void
    {
        $event = ExecutionEvent::started('plan-123', 5);

        $array = $event->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('timestamp', $array);
    }

    // =========================================================================
    // ExecutionAgentService Tests
    // =========================================================================

    public function test_execute_with_auto_approve_create_operation(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => "<?php\n\nclass TestService {\n    public function test(): bool\n    {\n        return true;\n    }\n}"]],
            ]),
        ]);

        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
            'file_operations' => [
                [
                    'type' => 'create',
                    'path' => 'app/Services/TestService.php',
                    'description' => 'Create test service',
                    'template_content' => '<?php class TestService {}',
                    'priority' => 1,
                    'dependencies' => [],
                ],
            ],
        ]);

        $events = iterator_to_array($this->service->execute($plan, ['auto_approve' => true]));

        $eventTypes = array_column($events, 'type');
        $this->assertContains('started', $eventTypes);
        $this->assertContains('file_started', $eventTypes);
        $this->assertContains('file_completed', $eventTypes);
        $this->assertContains('completed', $eventTypes);

        $this->assertFileExists($this->project->repo_path . '/app/Services/TestService.php');
    }

    public function test_execute_awaits_approval_when_not_auto(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
            'file_operations' => [
                [
                    'type' => 'create',
                    'path' => 'app/File.php',
                    'description' => 'Test',
                    'template_content' => '<?php class File {}',
                    'priority' => 1,
                    'dependencies' => [],
                ],
            ],
        ]);

        $events = iterator_to_array($this->service->execute($plan, ['auto_approve' => false]));

        $eventTypes = array_column($events, 'type');
        $this->assertContains('awaiting_approval', $eventTypes);
        $this->assertNotContains('completed', $eventTypes);
    }

    public function test_execute_stops_on_error_by_default(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
            'file_operations' => [
                [
                    'type' => 'modify',
                    'path' => 'app/NonExistent.php',
                    'description' => 'Modify non-existent file',
                    'changes' => [
                        [
                            'section' => 'methods',
                            'change_type' => 'add',
                            'content' => 'public function test() {}',
                            'explanation' => 'Add test method',
                        ],
                    ],
                    'priority' => 1,
                    'dependencies' => [],
                ],
            ],
        ]);

        $events = iterator_to_array($this->service->execute($plan, ['auto_approve' => true]));

        $eventTypes = array_column($events, 'type');
        $this->assertContains('file_failed', $eventTypes);
        $this->assertContains('execution_stopped', $eventTypes);
    }

    public function test_execute_rejects_non_approved_plan(): void
    {
        $plan = ExecutionPlan::factory()->pendingReview()->create([
            'project_id' => $this->project->id,
        ]);

        $events = iterator_to_array($this->service->execute($plan, ['auto_approve' => true]));

        $eventTypes = array_column($events, 'type');
        $this->assertContains('error', $eventTypes);
    }

    public function test_rollback_plan(): void
    {
        file_put_contents($this->project->repo_path . '/app/ToRollback.php', 'modified');

        // Use 'executing' status - it can transition to 'failed' after rollback
        $plan = ExecutionPlan::factory()->create([
            'project_id' => $this->project->id,
            'status' => PlanStatus::Executing,
        ]);

        FileExecution::factory()->create([
            'execution_plan_id' => $plan->id,
            'operation_type' => FileOperationType::Modify,
            'file_path' => 'app/ToRollback.php',
            'status' => FileExecutionStatus::Completed,
            'original_content' => 'original',
            'new_content' => 'modified',
        ]);

        $result = $this->service->rollbackPlan($plan);

        $this->assertTrue($result->success);
        $this->assertContains('app/ToRollback.php', $result->rolledBack);
        $this->assertEquals('original', file_get_contents($this->project->repo_path . '/app/ToRollback.php'));
    }

    public function test_rollback_created_file_deletes_it(): void
    {
        // Use 'executing' status - it can transition to 'failed' after rollback
        $plan = ExecutionPlan::factory()->create([
            'project_id' => $this->project->id,
            'status' => PlanStatus::Executing,
        ]);

        file_put_contents($this->project->repo_path . '/app/CreatedFile.php', 'created content');

        FileExecution::factory()->create([
            'execution_plan_id' => $plan->id,
            'operation_type' => FileOperationType::Create,
            'file_path' => 'app/CreatedFile.php',
            'status' => FileExecutionStatus::Completed,
            'original_content' => null,
            'new_content' => 'created content',
        ]);

        $result = $this->service->rollbackPlan($plan);

        $this->assertTrue($result->success);
        $this->assertFileDoesNotExist($this->project->repo_path . '/app/CreatedFile.php');
    }

    public function test_skip_file(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
        ]);

        $execution = FileExecution::factory()->pending()->create([
            'execution_plan_id' => $plan->id,
        ]);

        $this->service->skipFile($execution, 'User chose to skip');

        $this->assertEquals(FileExecutionStatus::Skipped, $execution->fresh()->status);
    }

    // =========================================================================
    // Integration Tests
    // =========================================================================

    public function test_full_execution_flow_with_modify(): void
    {
        // Mock the Claude API response
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => "<?php\n\nclass User\n{\n    public function newMethod(): void\n    {\n        // New method\n    }\n}"]],
            ]),
        ]);

        // Create the file to be modified
        $originalContent = "<?php\n\nclass User\n{\n}";
        file_put_contents($this->project->repo_path . '/app/User.php', $originalContent);

        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
            'file_operations' => [
                [
                    'type' => 'modify',
                    'path' => 'app/User.php',
                    'description' => 'Add new method',
                    'changes' => [
                        [
                            'section' => 'methods',
                            'change_type' => 'add',
                            'content' => 'public function newMethod() {}',
                            'explanation' => 'Adding new method',
                        ],
                    ],
                    'priority' => 1,
                    'dependencies' => [],
                ],
            ],
        ]);

        // Create a partial mock of the service to handle template loading gracefully
        $mockService = $this->getMockBuilder(ExecutionAgentService::class)
            ->setConstructorArgs([
                $this->fileWriter,
                $this->diffService,
                app(PromptTemplateService::class),
            ])
            ->onlyMethods(['generateModifications'])
            ->getMock();

        // Mock generateModifications to return successful result with new content
        $newContent = "<?php\n\nclass User\n{\n    public function newMethod(): void\n    {\n        // New method\n    }\n}";
        $diff = $this->diffService->generateDiff($originalContent, $newContent, 'app/User.php');

        $mockService->method('generateModifications')
            ->willReturn(ModificationResult::success($newContent, [
                ['section' => 'methods', 'type' => 'add', 'applied' => true, 'explanation' => 'Adding new method']
            ], $diff));

        $events = iterator_to_array($mockService->execute($plan, ['auto_approve' => true]));
        $eventTypes = array_column($events, 'type');

        $this->assertContains('completed', $eventTypes);

        $content = file_get_contents($this->project->repo_path . '/app/User.php');
        $this->assertStringContainsString('newMethod', $content);

        $execution = FileExecution::where('execution_plan_id', $plan->id)->first();
        $this->assertEquals(FileExecutionStatus::Completed, $execution->status);
        $this->assertNotNull($execution->diff);
        $this->assertNotNull($execution->original_content);
    }

    public function test_execution_plan_progress_tracking(): void
    {
        $plan = ExecutionPlan::factory()->approved()->create([
            'project_id' => $this->project->id,
            'file_operations' => [
                ['type' => 'create', 'path' => 'app/A.php', 'template_content' => '<?php', 'priority' => 1, 'description' => 'A', 'dependencies' => []],
                ['type' => 'create', 'path' => 'app/B.php', 'template_content' => '<?php', 'priority' => 2, 'description' => 'B', 'dependencies' => []],
            ],
        ]);

        FileExecution::factory()->completed()->create([
            'execution_plan_id' => $plan->id,
            'file_operation_index' => 0,
        ]);
        FileExecution::factory()->pending()->create([
            'execution_plan_id' => $plan->id,
            'file_operation_index' => 1,
        ]);

        $progress = $plan->getExecutionProgress();

        $this->assertEquals(2, $progress['total']);
        $this->assertEquals(1, $progress['completed']);
        $this->assertEquals(1, $progress['pending']);
        $this->assertEquals(50.0, $progress['percentage']);
    }
}
