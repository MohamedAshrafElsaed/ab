# Phase 5: Execution Agent System

## Overview

Phase 5 introduces the Execution Agent - the system that takes approved execution plans from Phase 4 and performs the actual file operations. It provides real-time progress streaming, rollback capabilities, and comprehensive error handling.

## Architecture

```
Approved ExecutionPlan
    → ExecutionAgentService
        → FileExecution (per file)
            → FileWriterService (create/modify/delete/move)
            → DiffGeneratorService (diff generation)
            → Claude API (code generation when needed)
        → ExecutionEvent (streaming progress)
    → Completed/Failed Plan
    → Optional Rollback
```

## Components

### 1. Database Schema

**Table: `file_executions`**

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| execution_plan_id | uuid | Foreign key to execution_plans |
| file_operation_index | int | Order in plan |
| operation_type | enum | create, modify, delete, rename, move |
| file_path | string | Target file path |
| new_file_path | string | For rename/move operations |
| status | enum | pending, in_progress, completed, failed, skipped, rolled_back |
| original_content | longtext | Original file content (for rollback) |
| new_content | longtext | New file content after execution |
| diff | longtext | Unified diff format |
| error_message | text | Error details if failed |
| user_approved | boolean | User explicitly approved |
| auto_approved | boolean | System auto-approved |
| backup_path | string | Path to backup file |
| execution_started_at | timestamp | When execution started |
| execution_completed_at | timestamp | When execution finished |
| metadata | json | Additional execution data |

### 2. Enums

#### FileExecutionStatus

```php
enum FileExecutionStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case RolledBack = 'rolled_back';
}
```

**Helper Methods:**
- `label()` - Human-readable name
- `color()` - UI color (gray, blue, green, red, yellow, orange)
- `icon()` - Icon name for UI
- `isTerminal()` - Whether this is a final state
- `canRollback()` - Whether rollback is possible
- `canRetry()` - Whether retry is possible

### 3. DTOs

#### WriteResult

Result of file write operations.

```php
// Success
$result = WriteResult::success(
    path: 'app/Test.php',
    backupPath: '/backups/test.php',
    originalContent: 'original code'
);

// Failure
$result = WriteResult::failure('app/Test.php', 'Permission denied');

$result->success;        // bool
$result->path;           // string
$result->backupPath;     // ?string
$result->originalContent; // ?string
$result->error;          // ?string
```

#### ModificationResult

Result of applying modifications to a file.

```php
$result = ModificationResult::success(
    modifiedContent: 'new code',
    changesApplied: [
        ['section' => 'methods', 'type' => 'add', 'applied' => true, 'explanation' => '...']
    ],
    diff: '--- a/file\n+++ b/file\n...'
);

$result->getTotalChanges();    // int
$result->getAppliedCount();    // int
$result->getFailedCount();     // int
$result->allChangesApplied();  // bool
```

#### FileExecutionResult

Result of executing a single file operation.

```php
$result = FileExecutionResult::success(
    newContent: '<?php class Test {}',
    diff: '--- a/file\n+++ b/file',
    backupPath: '/backup/test.php'
);

$result->hasDiff();           // bool
$result->hasBackup();         // bool
$result->wasSkipped();        // bool
$result->getContentLength();  // int
$result->getDiffLineCount();  // int
```

#### RollbackResult

Result of rollback operations.

```php
$result = RollbackResult::fromResults(
    rolledBack: ['file1.php', 'file2.php'],
    failed: [['path' => 'file3.php', 'error' => 'Permission denied']],
    skipped: ['file4.php']
);

$result->getRolledBackCount();  // int
$result->getFailedCount();      // int
$result->getSkippedCount();     // int
$result->isPartialSuccess();    // bool
$result->getSummary();          // "2 rolled back, 1 failed, 1 skipped"
```

### 4. Services

#### ExecutionAgentService

The main orchestrator for plan execution.

```php
use App\Services\AI\ExecutionAgentService;

$executor = app(ExecutionAgentService::class);

// Execute with auto-approve
foreach ($executor->execute($plan, ['auto_approve' => true]) as $event) {
    broadcast($event);
}

// Execute with manual approval (pauses at each file)
foreach ($executor->execute($plan, ['auto_approve' => false]) as $event) {
    if ($event->type === 'awaiting_approval') {
        // Wait for user approval, then continue
    }
}

// Continue after approval
foreach ($executor->continueExecution($fileExecution, $options) as $event) {
    broadcast($event);
}

// Rollback completed plan
$result = $executor->rollbackPlan($plan);

// Skip a file
$executor->skipFile($execution, 'User chose to skip');
```

#### FileWriterService

Safe file operations with automatic backup.

```php
use App\Services\Files\FileWriterService;

$writer = app(FileWriterService::class);

// Create new file
$result = $writer->createFile($project, 'app/NewService.php', $content);

// Modify existing file
$result = $writer->modifyFile($project, 'app/User.php', $newContent);
// $result->backupPath contains backup location
// $result->originalContent contains original

// Delete file (with backup)
$result = $writer->deleteFile($project, 'app/OldService.php');

// Move/rename file
$result = $writer->moveFile($project, 'app/Old.php', 'app/New.php');

// Restore from backup
$success = $writer->restoreFromBackup($backupPath, $targetPath);
```

#### DiffGeneratorService

Generate and parse unified diffs.

```php
use App\Services\Files\DiffGeneratorService;

$diffService = app(DiffGeneratorService::class);

// Generate diff
$diff = $diffService->generateDiff($original, $modified, 'file.php');

// Parse diff into structured changes
$changes = $diffService->parseDiff($diff);
// Returns: [['type' => 'added', 'old_line' => null, 'new_line' => 5, 'content' => 'new code'], ...]

// Get statistics
$stats = $diffService->getStats($diff);
// Returns: ['added' => 10, 'removed' => 5, 'changed_hunks' => 3]

// Generate HTML for display
$html = $diffService->generateHtmlDiff($diff);
```

### 5. Events

#### ExecutionEvent

Broadcastable events for real-time progress.

```php
use App\Events\ExecutionEvent;

// Event types
ExecutionEvent::TYPE_STARTED          // Plan execution started
ExecutionEvent::TYPE_FILE_STARTED     // Starting a file operation
ExecutionEvent::TYPE_FILE_GENERATING  // Generating code with Claude
ExecutionEvent::TYPE_FILE_COMPLETED   // File operation completed
ExecutionEvent::TYPE_FILE_FAILED      // File operation failed
ExecutionEvent::TYPE_AWAITING_APPROVAL // Waiting for user approval
ExecutionEvent::TYPE_FILE_APPROVED    // User approved file
ExecutionEvent::TYPE_FILE_SKIPPED     // File was skipped
ExecutionEvent::TYPE_EXECUTION_STOPPED // Execution stopped (error or user)
ExecutionEvent::TYPE_COMPLETED        // All operations complete
ExecutionEvent::TYPE_ROLLBACK_STARTED // Rollback started
ExecutionEvent::TYPE_ROLLBACK_COMPLETED // Rollback finished
ExecutionEvent::TYPE_ERROR            // Error occurred

// Factory methods
ExecutionEvent::started($planId, $totalFiles);
ExecutionEvent::fileCompleted($planId, $index, $path, $diff);
ExecutionEvent::fileFailed($planId, $index, $path, $error);
ExecutionEvent::awaitingApproval($planId, $executionId, $path, $diff);
ExecutionEvent::completed($planId, $filesCompleted, $filesFailed);

// Broadcasting
$event->broadcastOn(); // PrivateChannel('execution.{plan_id}')
```

### 6. Model: FileExecution

```php
use App\Models\FileExecution;

// Create execution record
$execution = FileExecution::create([
    'execution_plan_id' => $plan->id,
    'file_operation_index' => 0,
    'operation_type' => FileOperationType::Create,
    'file_path' => 'app/Service.php',
]);

// Status transitions
$execution->markInProgress();
$execution->markCompleted($newContent, $diff);
$execution->markFailed('Error message');
$execution->markSkipped('Reason');
$execution->markRolledBack();

// Approval
$execution->approve();      // User approval
$execution->autoApprove();  // System approval
$execution->skip('reason');

// Query scopes
FileExecution::pending()->get();
FileExecution::completed()->get();
FileExecution::failed()->get();
FileExecution::rollbackable()->get();
FileExecution::awaitingApproval()->get();

// Accessors
$execution->can_rollback;      // bool
$execution->is_approved;       // bool
$execution->is_terminal;       // bool
$execution->duration_seconds;  // ?float
$execution->diff_lines;        // array of parsed diff lines
$execution->added_lines_count; // int
$execution->removed_lines_count; // int
```

## Configuration

**config/execution.php:**

```php
return [
    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('EXECUTION_MODEL', 'claude-sonnet-4-5-20250514'),
        'max_tokens' => env('EXECUTION_MAX_TOKENS', 8192),
        'timeout' => env('EXECUTION_TIMEOUT', 120),
    ],
    
    'defaults' => [
        'auto_approve' => false,
        'stop_on_error' => true,
        'create_backups' => true,
    ],
    
    'backup' => [
        'enabled' => true,
        'path' => storage_path('app/backups'),
        'retention_days' => 7,
    ],
];
```

## Usage Flow

### 1. Execute Approved Plan

```php
$plan = ExecutionPlan::find($planId);

if (!$plan->can_execute) {
    throw new Exception('Plan not approved');
}

$executor = app(ExecutionAgentService::class);

foreach ($executor->execute($plan, ['auto_approve' => true]) as $event) {
    // Stream events to frontend
    broadcast($event);
    
    // Log progress
    if ($event->type === 'file_completed') {
        Log::info("Completed: {$event->data['path']}");
    }
}
```

### 2. Manual Approval Flow

```php
// Start execution (will pause at first file)
foreach ($executor->execute($plan, ['auto_approve' => false]) as $event) {
    if ($event->type === 'awaiting_approval') {
        // Show diff to user and wait
        return response()->json([
            'execution_id' => $event->data['execution_id'],
            'path' => $event->data['path'],
            'diff' => $event->data['diff'],
        ]);
    }
}

// After user approves
$execution = FileExecution::find($executionId);
foreach ($executor->continueExecution($execution, $options) as $event) {
    broadcast($event);
}
```

### 3. Rollback

```php
$executor = app(ExecutionAgentService::class);

$result = $executor->rollbackPlan($plan);

if ($result->success) {
    Log::info("Rolled back {$result->getRolledBackCount()} files");
} else {
    Log::warning("Partial rollback", [
        'rolled_back' => $result->rolledBack,
        'failed' => $result->failed,
    ]);
}
```

## Testing

```bash
# Run all Phase 5 tests
php artisan test --filter=ExecutionAgentServiceTest
php artisan test --filter=ExecutionDTOsTest

# Run specific test groups
php artisan test --filter=test_file_writer
php artisan test --filter=test_diff_generator
php artisan test --filter=test_rollback
```

## Files Created

| File | Purpose |
|------|---------|
| `database/migrations/..._create_file_executions_table.php` | Database schema |
| `app/Enums/FileExecutionStatus.php` | Execution status states |
| `app/Models/FileExecution.php` | Eloquent model |
| `app/DTOs/WriteResult.php` | File write result DTO |
| `app/DTOs/ModificationResult.php` | Modification result DTO |
| `app/DTOs/FileExecutionResult.php` | Execution result DTO |
| `app/DTOs/RollbackResult.php` | Rollback result DTO |
| `app/Events/ExecutionEvent.php` | Broadcastable event |
| `app/Services/Files/FileWriterService.php` | Safe file operations |
| `app/Services/Files/DiffGeneratorService.php` | Diff generation/parsing |
| `app/Services/AI/ExecutionAgentService.php` | Main execution service |
| `app/Providers/ExecutionAgentServiceProvider.php` | Service registration |
| `config/execution.php` | Configuration |
| `resources/prompts/system/code_executor.md` | Code generation prompt |
| `database/factories/FileExecutionFactory.php` | Test factory |
| `tests/Feature/ExecutionAgentServiceTest.php` | Feature tests |
| `tests/Unit/ExecutionDTOsTest.php` | DTO unit tests |

## Integration with Earlier Phases

```php
// Full pipeline: Intent → Plan → Execute

// Phase 1: Classify intent
$intent = $intentAnalyzer->analyze($project, $userMessage);

// Phase 3: Retrieve context
$context = $contextRetrieval->retrieve($project, $intent, $userMessage);

// Phase 4: Generate plan
$plan = $planningAgent->generatePlan($project, $intent, $userMessage);

// User reviews and approves
$plan->approve($userId);

// Phase 5: Execute plan
$executor = app(ExecutionAgentService::class);
foreach ($executor->execute($plan, ['auto_approve' => true]) as $event) {
    broadcast($event);
}

// Check results
if ($plan->fresh()->status === PlanStatus::Completed) {
    // Success - all files modified
} else {
    // Handle failure or rollback
    $executor->rollbackPlan($plan);
}
```

## Next Steps: Phase 6

Phase 6 will implement:
- **Git Integration** - Commit changes, create branches, open PRs
- **Change Verification** - Run tests after modifications
- **Orchestrator Service** - Coordinate the full pipeline end-to-end

## Changelog

### v1.0.0 (Initial Release)

- FileExecution model with status tracking
- WriteResult, ModificationResult, FileExecutionResult, RollbackResult DTOs
- FileWriterService for safe file operations with automatic backup
- DiffGeneratorService for unified diff generation
- ExecutionAgentService with streaming execution
- ExecutionEvent for real-time progress broadcasting
- Rollback support for completed operations
- Manual and auto-approval modes
- Comprehensive test suite (45+ tests)
