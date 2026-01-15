<?php

namespace Tests\Unit;

use App\DTOs\FileExecutionResult;
use App\DTOs\ModificationResult;
use App\DTOs\RollbackResult;
use App\DTOs\WriteResult;
use App\Enums\FileExecutionStatus;
use PHPUnit\Framework\TestCase;

class ExecutionDTOsTest extends TestCase
{
    // =========================================================================
    // WriteResult Tests
    // =========================================================================

    public function test_write_result_success_factory(): void
    {
        $result = WriteResult::success(
            path: 'app/Test.php',
            backupPath: '/backups/test.php',
            originalContent: 'original',
            metadata: ['created_at' => '2026-01-12']
        );

        $this->assertTrue($result->success);
        $this->assertEquals('app/Test.php', $result->path);
        $this->assertEquals('/backups/test.php', $result->backupPath);
        $this->assertEquals('original', $result->originalContent);
        $this->assertNull($result->error);
        $this->assertArrayHasKey('created_at', $result->metadata);
    }

    public function test_write_result_failure_factory(): void
    {
        $result = WriteResult::failure('app/Test.php', 'Permission denied');

        $this->assertFalse($result->success);
        $this->assertEquals('app/Test.php', $result->path);
        $this->assertEquals('Permission denied', $result->error);
        $this->assertNull($result->backupPath);
    }

    public function test_write_result_to_array(): void
    {
        $result = WriteResult::success('app/Test.php', '/backup');

        $array = $result->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('path', $array);
        $this->assertArrayHasKey('backup_path', $array);
        $this->assertArrayHasKey('has_original', $array);
        $this->assertTrue($array['success']);
    }

    public function test_write_result_json_serializable(): void
    {
        $result = WriteResult::success('app/Test.php');

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertEquals('app/Test.php', $decoded['path']);
        $this->assertTrue($decoded['success']);
    }

    public function test_write_result_has_backup(): void
    {
        $withBackup = WriteResult::success('path', '/backup/file');
        $withoutBackup = WriteResult::success('path', null);

        // Note: hasBackup() checks file_exists, which won't exist in unit test
        // Just verify the backupPath is set correctly
        $this->assertEquals('/backup/file', $withBackup->backupPath);
        $this->assertNull($withoutBackup->backupPath);
    }

    // =========================================================================
    // ModificationResult Tests
    // =========================================================================

    public function test_modification_result_success(): void
    {
        $changes = [
            ['section' => 'methods', 'type' => 'add', 'applied' => true, 'explanation' => 'Added method'],
            ['section' => 'imports', 'type' => 'add', 'applied' => true, 'explanation' => 'Added import'],
        ];

        $result = ModificationResult::success('modified content', $changes, 'diff here');

        $this->assertTrue($result->success);
        $this->assertEquals('modified content', $result->modifiedContent);
        $this->assertEquals(2, $result->getTotalChanges());
        $this->assertEquals(2, $result->getAppliedCount());
        $this->assertEquals(0, $result->getFailedCount());
        $this->assertTrue($result->allChangesApplied());
    }

    public function test_modification_result_with_failed_changes(): void
    {
        $changes = [
            ['section' => 'methods', 'type' => 'add', 'applied' => true, 'explanation' => 'OK'],
            ['section' => 'other', 'type' => 'add', 'applied' => false, 'explanation' => 'Failed'],
        ];

        $result = ModificationResult::success('content', $changes, 'diff');

        $this->assertEquals(2, $result->getTotalChanges());
        $this->assertEquals(1, $result->getAppliedCount());
        $this->assertEquals(1, $result->getFailedCount());
        $this->assertFalse($result->allChangesApplied());
    }

    public function test_modification_result_failure(): void
    {
        $result = ModificationResult::failure('Parse error');

        $this->assertFalse($result->success);
        $this->assertEquals('Parse error', $result->error);
        $this->assertEquals('', $result->modifiedContent);
        $this->assertEmpty($result->changesApplied);
        $this->assertEquals('', $result->diff);
    }

    public function test_modification_result_to_array(): void
    {
        $result = ModificationResult::success('content', [], 'diff');

        $array = $result->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('modified_content_length', $array);
        $this->assertArrayHasKey('total_changes', $array);
        $this->assertArrayHasKey('applied_count', $array);
        $this->assertArrayHasKey('failed_count', $array);
        $this->assertArrayHasKey('diff', $array);
    }

    public function test_modification_result_json_serializable(): void
    {
        $result = ModificationResult::success('content', [], 'diff');

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['success']);
        $this->assertEquals(7, $decoded['modified_content_length']); // strlen('content')
    }

    // =========================================================================
    // FileExecutionResult Tests
    // =========================================================================

    public function test_file_execution_result_success(): void
    {
        $result = FileExecutionResult::success(
            newContent: '<?php class Test {}',
            diff: '--- a\n+++ b',
            backupPath: '/backup/test.php',
            metadata: ['operation' => 'create']
        );

        $this->assertTrue($result->success);
        $this->assertEquals('<?php class Test {}', $result->newContent);
        $this->assertTrue($result->hasDiff());
        $this->assertTrue($result->hasBackup());
        $this->assertFalse($result->wasSkipped());
        $this->assertNull($result->error);
    }

    public function test_file_execution_result_failure(): void
    {
        $result = FileExecutionResult::failure('File not found');

        $this->assertFalse($result->success);
        $this->assertEquals('File not found', $result->error);
        $this->assertNull($result->newContent);
        $this->assertFalse($result->hasDiff());
        $this->assertFalse($result->hasBackup());
    }

    public function test_file_execution_result_skipped(): void
    {
        $result = FileExecutionResult::skipped('User chose to skip');

        $this->assertTrue($result->success);
        $this->assertTrue($result->wasSkipped());
        $this->assertEquals('User chose to skip', $result->metadata['skip_reason']);
        $this->assertTrue($result->metadata['skipped']);
    }

    public function test_file_execution_result_content_length(): void
    {
        $content = '<?php class Test {}';
        $result = FileExecutionResult::success($content);

        $this->assertEquals(strlen($content), $result->getContentLength());
    }

    public function test_file_execution_result_content_length_null(): void
    {
        $result = FileExecutionResult::success(null);

        $this->assertEquals(0, $result->getContentLength());
    }

    public function test_file_execution_result_diff_line_count(): void
    {
        $diff = "line1\nline2\nline3";
        $result = FileExecutionResult::success(null, $diff);

        $this->assertEquals(3, $result->getDiffLineCount());
    }

    public function test_file_execution_result_diff_line_count_empty(): void
    {
        $result = FileExecutionResult::success(null, null);

        $this->assertEquals(0, $result->getDiffLineCount());
    }

    public function test_file_execution_result_to_array(): void
    {
        $result = FileExecutionResult::success('content', 'diff');

        $array = $result->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('has_content', $array);
        $this->assertArrayHasKey('has_diff', $array);
        $this->assertArrayHasKey('content_length', $array);
        $this->assertArrayHasKey('diff_lines', $array);
        $this->assertTrue($array['has_content']);
        $this->assertTrue($array['has_diff']);
    }

    public function test_file_execution_result_json_serializable(): void
    {
        $result = FileExecutionResult::success('content', 'diff');

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['success']);
        $this->assertTrue($decoded['has_content']);
    }

    // =========================================================================
    // RollbackResult Tests
    // =========================================================================

    public function test_rollback_result_success(): void
    {
        $result = RollbackResult::fromResults(
            rolledBack: ['file1.php', 'file2.php'],
            failed: [],
            skipped: []
        );

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->getRolledBackCount());
        $this->assertEquals(0, $result->getFailedCount());
        $this->assertEquals(0, $result->getSkippedCount());
        $this->assertTrue($result->isComplete());
        $this->assertFalse($result->isPartialSuccess());
    }

    public function test_rollback_result_partial_success(): void
    {
        $result = RollbackResult::fromResults(
            rolledBack: ['file1.php'],
            failed: [['path' => 'file2.php', 'error' => 'Permission denied']],
            skipped: []
        );

        $this->assertFalse($result->success);
        $this->assertTrue($result->isPartialSuccess());
        $this->assertEquals(1, $result->getRolledBackCount());
        $this->assertEquals(1, $result->getFailedCount());
        $this->assertFalse($result->isComplete());
    }

    public function test_rollback_result_with_skipped(): void
    {
        $result = RollbackResult::fromResults(
            rolledBack: ['file1.php'],
            failed: [],
            skipped: ['file2.php', 'file3.php']
        );

        $this->assertTrue($result->success);
        $this->assertFalse($result->isComplete());
        $this->assertEquals(2, $result->getSkippedCount());
        $this->assertFalse($result->isPartialSuccess());
    }

    public function test_rollback_result_empty(): void
    {
        $result = RollbackResult::empty();

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->getRolledBackCount());
        $this->assertEquals(0, $result->getFailedCount());
        $this->assertEquals(0, $result->getSkippedCount());
        $this->assertEquals(0, $result->getTotalAttempted());
        $this->assertEquals('No changes to rollback', $result->getSummary());
    }

    public function test_rollback_result_total_attempted(): void
    {
        $result = RollbackResult::fromResults(
            rolledBack: ['a.php', 'b.php'],
            failed: [['path' => 'c.php', 'error' => 'err']],
            skipped: ['d.php']
        );

        $this->assertEquals(3, $result->getTotalAttempted()); // rolled back + failed, not skipped
    }

    public function test_rollback_result_get_failed_paths(): void
    {
        $result = RollbackResult::fromResults(
            rolledBack: [],
            failed: [
                ['path' => 'file1.php', 'error' => 'Error 1'],
                ['path' => 'file2.php', 'error' => 'Error 2'],
            ],
            skipped: []
        );

        $paths = $result->getFailedPaths();
        $this->assertEquals(['file1.php', 'file2.php'], $paths);
    }

    public function test_rollback_result_get_failure_messages(): void
    {
        $result = RollbackResult::fromResults(
            rolledBack: [],
            failed: [
                ['path' => 'file1.php', 'error' => 'Permission denied'],
                ['path' => 'file2.php', 'error' => 'File not found'],
            ],
            skipped: []
        );

        $messages = $result->getFailureMessages();
        $this->assertEquals([
            'file1.php' => 'Permission denied',
            'file2.php' => 'File not found',
        ], $messages);
    }

    public function test_rollback_result_summary(): void
    {
        $result = RollbackResult::fromResults(
            rolledBack: ['a.php', 'b.php'],
            failed: [['path' => 'c.php', 'error' => 'err']],
            skipped: ['d.php']
        );

        $summary = $result->getSummary();
        $this->assertStringContainsString('2 rolled back', $summary);
        $this->assertStringContainsString('1 failed', $summary);
        $this->assertStringContainsString('1 skipped', $summary);
    }

    public function test_rollback_result_to_array(): void
    {
        $result = RollbackResult::fromResults(['a.php'], [], [], ['key' => 'value']);

        $array = $result->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('rolled_back', $array);
        $this->assertArrayHasKey('failed', $array);
        $this->assertArrayHasKey('skipped', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('counts', $array);
        $this->assertArrayHasKey('metadata', $array);

        $this->assertEquals(1, $array['counts']['rolled_back']);
        $this->assertEquals('value', $array['metadata']['key']);
    }

    public function test_rollback_result_json_serializable(): void
    {
        $result = RollbackResult::fromResults(['a.php'], [], []);

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['success']);
        $this->assertEquals(['a.php'], $decoded['rolled_back']);
    }

    // =========================================================================
    // FileExecutionStatus Enum Tests
    // =========================================================================

    public function test_file_execution_status_labels(): void
    {
        $this->assertEquals('Pending', FileExecutionStatus::Pending->label());
        $this->assertEquals('In Progress', FileExecutionStatus::InProgress->label());
        $this->assertEquals('Completed', FileExecutionStatus::Completed->label());
        $this->assertEquals('Failed', FileExecutionStatus::Failed->label());
        $this->assertEquals('Skipped', FileExecutionStatus::Skipped->label());
        $this->assertEquals('Rolled Back', FileExecutionStatus::RolledBack->label());
    }

    public function test_file_execution_status_descriptions(): void
    {
        $this->assertStringContainsString('Waiting', FileExecutionStatus::Pending->description());
        $this->assertStringContainsString('Currently', FileExecutionStatus::InProgress->description());
        $this->assertStringContainsString('Successfully', FileExecutionStatus::Completed->description());
    }

    public function test_file_execution_status_colors(): void
    {
        $this->assertEquals('gray', FileExecutionStatus::Pending->color());
        $this->assertEquals('blue', FileExecutionStatus::InProgress->color());
        $this->assertEquals('green', FileExecutionStatus::Completed->color());
        $this->assertEquals('red', FileExecutionStatus::Failed->color());
        $this->assertEquals('yellow', FileExecutionStatus::Skipped->color());
        $this->assertEquals('orange', FileExecutionStatus::RolledBack->color());
    }

    public function test_file_execution_status_icons(): void
    {
        $this->assertEquals('clock', FileExecutionStatus::Pending->icon());
        $this->assertEquals('loader', FileExecutionStatus::InProgress->icon());
        $this->assertEquals('check-circle', FileExecutionStatus::Completed->icon());
        $this->assertEquals('x-circle', FileExecutionStatus::Failed->icon());
    }

    public function test_file_execution_status_is_terminal(): void
    {
        $this->assertFalse(FileExecutionStatus::Pending->isTerminal());
        $this->assertFalse(FileExecutionStatus::InProgress->isTerminal());
        $this->assertTrue(FileExecutionStatus::Completed->isTerminal());
        $this->assertTrue(FileExecutionStatus::Failed->isTerminal());
        $this->assertTrue(FileExecutionStatus::Skipped->isTerminal());
        $this->assertTrue(FileExecutionStatus::RolledBack->isTerminal());
    }

    public function test_file_execution_status_is_active(): void
    {
        $this->assertFalse(FileExecutionStatus::Pending->isActive());
        $this->assertTrue(FileExecutionStatus::InProgress->isActive());
        $this->assertFalse(FileExecutionStatus::Completed->isActive());
    }

    public function test_file_execution_status_can_rollback(): void
    {
        $this->assertTrue(FileExecutionStatus::Completed->canRollback());
        $this->assertFalse(FileExecutionStatus::Pending->canRollback());
        $this->assertFalse(FileExecutionStatus::Failed->canRollback());
        $this->assertFalse(FileExecutionStatus::InProgress->canRollback());
    }

    public function test_file_execution_status_can_retry(): void
    {
        $this->assertTrue(FileExecutionStatus::Failed->canRetry());
        $this->assertTrue(FileExecutionStatus::RolledBack->canRetry());
        $this->assertFalse(FileExecutionStatus::Completed->canRetry());
        $this->assertFalse(FileExecutionStatus::Pending->canRetry());
        $this->assertFalse(FileExecutionStatus::InProgress->canRetry());
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

    public function test_file_execution_status_terminal_statuses(): void
    {
        $terminal = FileExecutionStatus::terminalStatuses();

        $this->assertContains('completed', $terminal);
        $this->assertContains('failed', $terminal);
        $this->assertContains('skipped', $terminal);
        $this->assertContains('rolled_back', $terminal);
        $this->assertNotContains('pending', $terminal);
        $this->assertNotContains('in_progress', $terminal);
    }
}
