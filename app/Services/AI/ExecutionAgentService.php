<?php

namespace App\Services\AI;

use App\DTOs\FileExecutionResult;
use App\DTOs\FileOperation;
use App\DTOs\ModificationResult;
use App\DTOs\RollbackResult;
use App\DTOs\WriteResult;
use App\Enums\FileExecutionStatus;
use App\Enums\FileOperationType;
use App\Enums\PlanStatus;
use App\Events\ExecutionEvent;
use App\Models\ExecutionPlan;
use App\Models\FileExecution;
use App\Models\Project;
use App\Services\Files\DiffGeneratorService;
use App\Services\Files\FileWriterService;
use App\Services\Prompts\PromptTemplateService;
use Exception;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Execution Agent Service for executing approved plans.
 */
class ExecutionAgentService
{
    private array $config;

    public function __construct(
        private FileWriterService $fileWriter,
        private DiffGeneratorService $diffGenerator,
        private PromptTemplateService $templateService,
    ) {
        $this->config = config('execution', []);
    }

    /**
     * Execute an approved plan, yielding progress events.
     *
     * @return Generator<ExecutionEvent>
     */
    public function execute(ExecutionPlan $plan, array $options = []): Generator
    {
        $autoApprove = $options['auto_approve'] ?? false;
        $stopOnError = $options['stop_on_error'] ?? true;
        $project = $plan->project;

        if (!$plan->can_execute) {
            yield ExecutionEvent::error($plan->id, 'Plan is not in an executable state');
            return;
        }

        $operations = $plan->getOrderedFileOperations();
        yield ExecutionEvent::started($plan->id, count($operations));

        $plan->markExecuting();

        $completed = 0;
        $failed = 0;

        foreach ($operations as $index => $operationData) {
            $operation = FileOperation::fromArray($operationData);

            $execution = FileExecution::create([
                'execution_plan_id' => $plan->id,
                'file_operation_index' => $index,
                'operation_type' => $operation->type,
                'file_path' => $operation->path,
                'new_file_path' => $operation->newPath,
                'status' => FileExecutionStatus::Pending,
                'metadata' => ['started_at' => now()->toIso8601String()],
            ]);

            yield ExecutionEvent::fileStarted(
                $plan->id,
                $index,
                $operation->path,
                $operation->type->value,
                $operation->description
            );

            if ($autoApprove) {
                $execution->autoApprove();
            } else {
                yield ExecutionEvent::awaitingApproval(
                    $plan->id,
                    $execution->id,
                    $operation->path
                );
                return;
            }

            try {
                yield ExecutionEvent::fileGenerating($plan->id, $index, $operation->path);

                $result = $this->executeFileOperation($project, $operation, $execution, $plan);

                if ($result->success) {
                    yield ExecutionEvent::fileCompleted(
                        $plan->id,
                        $index,
                        $operation->path,
                        $result->diff
                    );
                    $completed++;
                } else {
                    yield ExecutionEvent::fileFailed(
                        $plan->id,
                        $index,
                        $operation->path,
                        $result->error ?? 'Unknown error'
                    );
                    $failed++;

                    if ($stopOnError) {
                        yield ExecutionEvent::executionStopped(
                            $plan->id,
                            'file_failed',
                            $operation->path
                        );
                        $plan->markFailed($result->error ?? 'File execution failed');
                        return;
                    }
                }
            } catch (Exception $e) {
                Log::error('ExecutionAgent: File operation failed', [
                    'plan_id' => $plan->id,
                    'path' => $operation->path,
                    'error' => $e->getMessage(),
                ]);

                $execution->markFailed($e->getMessage());
                $failed++;

                yield ExecutionEvent::fileFailed(
                    $plan->id,
                    $index,
                    $operation->path,
                    $e->getMessage()
                );

                if ($stopOnError) {
                    yield ExecutionEvent::executionStopped($plan->id, 'exception', $operation->path);
                    $plan->markFailed($e->getMessage());
                    return;
                }
            }
        }

        if ($failed === 0) {
            $plan->markCompleted();
        } else {
            $plan->markFailed("Completed with {$failed} failures");
        }

        yield ExecutionEvent::completed($plan->id, $completed, $failed);
    }

    /**
     * Continue execution after user approval.
     *
     * @return Generator<ExecutionEvent>
     */
    public function continueExecution(FileExecution $execution, array $options = []): Generator
    {
        $plan = $execution->executionPlan;
        $project = $plan->project;
        $autoApprove = $options['auto_approve'] ?? false;
        $stopOnError = $options['stop_on_error'] ?? true;

        $execution->approve();

        yield ExecutionEvent::fileApproved($plan->id, $execution->id, $execution->file_path);

        $operationData = $plan->file_operations[$execution->file_operation_index] ?? null;
        if (!$operationData) {
            yield ExecutionEvent::error($plan->id, 'Operation not found in plan');
            return;
        }

        $operation = FileOperation::fromArray($operationData);

        try {
            yield ExecutionEvent::fileGenerating($plan->id, $execution->file_operation_index, $operation->path);

            $result = $this->executeFileOperation($project, $operation, $execution, $plan);

            if ($result->success) {
                yield ExecutionEvent::fileCompleted(
                    $plan->id,
                    $execution->file_operation_index,
                    $operation->path,
                    $result->diff
                );
            } else {
                yield ExecutionEvent::fileFailed(
                    $plan->id,
                    $execution->file_operation_index,
                    $operation->path,
                    $result->error ?? 'Unknown error'
                );

                if ($stopOnError) {
                    yield ExecutionEvent::executionStopped($plan->id, 'file_failed', $operation->path);
                    $plan->markFailed($result->error ?? 'File execution failed');
                    return;
                }
            }
        } catch (Exception $e) {
            $execution->markFailed($e->getMessage());
            yield ExecutionEvent::fileFailed(
                $plan->id,
                $execution->file_operation_index,
                $operation->path,
                $e->getMessage()
            );

            if ($stopOnError) {
                yield ExecutionEvent::executionStopped($plan->id, 'exception', $operation->path);
                $plan->markFailed($e->getMessage());
                return;
            }
        }

        $remainingOperations = array_slice($plan->getOrderedFileOperations(), $execution->file_operation_index + 1);

        foreach ($remainingOperations as $relIndex => $opData) {
            $index = $execution->file_operation_index + 1 + $relIndex;
            $op = FileOperation::fromArray($opData);

            foreach ($this->executeRemainingOperation($plan, $project, $op, $index, $autoApprove, $stopOnError) as $event) {
                yield $event;

                if ($event->type === ExecutionEvent::TYPE_AWAITING_APPROVAL ||
                    $event->type === ExecutionEvent::TYPE_EXECUTION_STOPPED) {
                    return;
                }
            }
        }

        $completedCount = $plan->fileExecutions()->completed()->count();
        $failedCount = $plan->fileExecutions()->failed()->count();

        if ($failedCount === 0) {
            $plan->markCompleted();
        } else {
            $plan->markFailed("Completed with {$failedCount} failures");
        }

        yield ExecutionEvent::completed($plan->id, $completedCount, $failedCount);
    }

    /**
     * Execute remaining operation helper.
     *
     * @return Generator<ExecutionEvent>
     */
    private function executeRemainingOperation(
        ExecutionPlan $plan,
        Project $project,
        FileOperation $operation,
        int $index,
        bool $autoApprove,
        bool $stopOnError
    ): Generator {
        $execution = FileExecution::create([
            'execution_plan_id' => $plan->id,
            'file_operation_index' => $index,
            'operation_type' => $operation->type,
            'file_path' => $operation->path,
            'new_file_path' => $operation->newPath,
            'status' => FileExecutionStatus::Pending,
        ]);

        yield ExecutionEvent::fileStarted(
            $plan->id,
            $index,
            $operation->path,
            $operation->type->value,
            $operation->description
        );

        if ($autoApprove) {
            $execution->autoApprove();
        } else {
            yield ExecutionEvent::awaitingApproval($plan->id, $execution->id, $operation->path);
            return;
        }

        try {
            yield ExecutionEvent::fileGenerating($plan->id, $index, $operation->path);

            $result = $this->executeFileOperation($project, $operation, $execution, $plan);

            if ($result->success) {
                yield ExecutionEvent::fileCompleted($plan->id, $index, $operation->path, $result->diff);
            } else {
                yield ExecutionEvent::fileFailed($plan->id, $index, $operation->path, $result->error ?? 'Unknown error');

                if ($stopOnError) {
                    yield ExecutionEvent::executionStopped($plan->id, 'file_failed', $operation->path);
                }
            }
        } catch (Exception $e) {
            $execution->markFailed($e->getMessage());
            yield ExecutionEvent::fileFailed($plan->id, $index, $operation->path, $e->getMessage());

            if ($stopOnError) {
                yield ExecutionEvent::executionStopped($plan->id, 'exception', $operation->path);
            }
        }
    }

    /**
     * Execute a single file operation.
     */
    public function executeFileOperation(
        Project $project,
        FileOperation $operation,
        FileExecution $execution,
        ExecutionPlan $plan
    ): FileExecutionResult {
        $execution->markInProgress();

        return match ($operation->type) {
            FileOperationType::Create => $this->executeCreate($project, $operation, $execution, $plan),
            FileOperationType::Modify => $this->executeModify($project, $operation, $execution, $plan),
            FileOperationType::Delete => $this->executeDelete($project, $operation, $execution),
            FileOperationType::Rename, FileOperationType::Move => $this->executeMove($project, $operation, $execution),
        };
    }

    /**
     * Execute create operation.
     */
    private function executeCreate(
        Project $project,
        FileOperation $operation,
        FileExecution $execution,
        ExecutionPlan $plan
    ): FileExecutionResult {
        $content = $operation->templateContent;

        if (empty($content) || $this->needsGeneration($content)) {
            $content = $this->generateFileContent($project, $operation, $plan);
        }

        $writeResult = $this->fileWriter->createFile($project, $operation->path, $content);

        if (!$writeResult->success) {
            $execution->markFailed($writeResult->error ?? 'Failed to create file');
            return FileExecutionResult::failure($writeResult->error ?? 'Failed to create file');
        }

        $diff = $this->diffGenerator->generateDiff('', $content, $operation->path);

        $execution->markCompleted($content, $diff);

        return FileExecutionResult::success($content, $diff, null, [
            'operation' => 'create',
            'path' => $operation->path,
        ]);
    }

    /**
     * Execute modify operation.
     */
    private function executeModify(
        Project $project,
        FileOperation $operation,
        FileExecution $execution,
        ExecutionPlan $plan
    ): FileExecutionResult {
        $currentContent = $this->fileWriter->readFile($project, $operation->path);

        if ($currentContent === null) {
            $execution->markFailed('File not found');
            return FileExecutionResult::failure('File not found: ' . $operation->path);
        }

        $execution->setOriginalContent($currentContent);

        $modResult = $this->generateModifications($project, $operation, $currentContent, $plan);

        if (!$modResult->success) {
            $execution->markFailed($modResult->error ?? 'Failed to generate modifications');
            return FileExecutionResult::failure($modResult->error ?? 'Failed to generate modifications');
        }

        $writeResult = $this->fileWriter->modifyFile($project, $operation->path, $modResult->modifiedContent);

        if (!$writeResult->success) {
            $execution->markFailed($writeResult->error ?? 'Failed to write modifications');
            return FileExecutionResult::failure($writeResult->error ?? 'Failed to write modifications');
        }

        if ($writeResult->backupPath) {
            $execution->setBackupPath($writeResult->backupPath);
        }

        $execution->markCompleted($modResult->modifiedContent, $modResult->diff);

        return FileExecutionResult::success(
            $modResult->modifiedContent,
            $modResult->diff,
            $writeResult->backupPath,
            ['changes_applied' => $modResult->changesApplied]
        );
    }

    /**
     * Execute delete operation.
     */
    private function executeDelete(
        Project $project,
        FileOperation $operation,
        FileExecution $execution
    ): FileExecutionResult {
        $currentContent = $this->fileWriter->readFile($project, $operation->path);

        if ($currentContent !== null) {
            $execution->setOriginalContent($currentContent);
        }

        $writeResult = $this->fileWriter->deleteFile($project, $operation->path);

        if (!$writeResult->success) {
            $execution->markFailed($writeResult->error ?? 'Failed to delete file');
            return FileExecutionResult::failure($writeResult->error ?? 'Failed to delete file');
        }

        if ($writeResult->backupPath) {
            $execution->setBackupPath($writeResult->backupPath);
        }

        $diff = $currentContent ? $this->diffGenerator->generateDiff($currentContent, '', $operation->path) : '';

        $execution->markCompleted('', $diff);

        return FileExecutionResult::success(null, $diff, $writeResult->backupPath, [
            'operation' => 'delete',
            'path' => $operation->path,
        ]);
    }

    /**
     * Execute move/rename operation.
     */
    private function executeMove(
        Project $project,
        FileOperation $operation,
        FileExecution $execution
    ): FileExecutionResult {
        if (!$operation->newPath) {
            $execution->markFailed('New path not specified for move operation');
            return FileExecutionResult::failure('New path not specified');
        }

        $currentContent = $this->fileWriter->readFile($project, $operation->path);
        if ($currentContent !== null) {
            $execution->setOriginalContent($currentContent);
        }

        $writeResult = $this->fileWriter->moveFile($project, $operation->path, $operation->newPath);

        if (!$writeResult->success) {
            $execution->markFailed($writeResult->error ?? 'Failed to move file');
            return FileExecutionResult::failure($writeResult->error ?? 'Failed to move file');
        }

        if ($writeResult->backupPath) {
            $execution->setBackupPath($writeResult->backupPath);
        }

        $execution->markCompleted($currentContent ?? '', null);

        return FileExecutionResult::success($currentContent, null, $writeResult->backupPath, [
            'operation' => 'move',
            'old_path' => $operation->path,
            'new_path' => $operation->newPath,
        ]);
    }

    /**
     * Generate content for a new file using Claude.
     */
    public function generateFileContent(
        Project $project,
        FileOperation $operation,
        ExecutionPlan $plan
    ): string {
        $systemPrompt = $this->buildExecutorSystemPrompt($project);
        $userPrompt = $this->buildCreatePrompt($operation, $plan);

        $response = $this->callClaude($systemPrompt, $userPrompt);

        return $this->extractCode($response);
    }

    /**
     * Generate modifications for an existing file.
     */
    public function generateModifications(
        Project $project,
        FileOperation $operation,
        string $currentContent,
        ExecutionPlan $plan
    ): ModificationResult {
        $systemPrompt = $this->buildExecutorSystemPrompt($project);
        $userPrompt = $this->buildModifyPrompt($operation, $currentContent, $plan);

        try {
            $response = $this->callClaude($systemPrompt, $userPrompt);
            $modifiedContent = $this->extractCode($response);

            if (empty($modifiedContent)) {
                return ModificationResult::failure('Claude returned empty content');
            }

            $diff = $this->diffGenerator->generateDiff($currentContent, $modifiedContent, $operation->path);

            $changesApplied = [];
            foreach ($operation->changes ?? [] as $change) {
                $changesApplied[] = [
                    'section' => $change->section ?? 'unknown',
                    'type' => $change->changeType ?? 'unknown',
                    'applied' => true,
                    'explanation' => $change->explanation ?? '',
                ];
            }

            return ModificationResult::success($modifiedContent, $changesApplied, $diff);
        } catch (Exception $e) {
            return ModificationResult::failure($e->getMessage());
        }
    }

    /**
     * Rollback all completed operations in a plan.
     *
     * FIX: Don't change plan status - rollback is about file changes, not plan state.
     * The plan status should be managed separately by the caller.
     */
    public function rollbackPlan(ExecutionPlan $plan): RollbackResult
    {
        $executions = $plan->fileExecutions()
            ->rollbackable()
            ->orderByDesc('file_operation_index')
            ->get();

        if ($executions->isEmpty()) {
            return RollbackResult::empty();
        }

        $rolledBack = [];
        $failed = [];
        $skipped = [];

        foreach ($executions as $execution) {
            if (!$execution->can_rollback) {
                $skipped[] = $execution->file_path;
                continue;
            }

            $success = $this->rollbackFile($execution);

            if ($success) {
                $rolledBack[] = $execution->file_path;
            } else {
                $failed[] = [
                    'path' => $execution->file_path,
                    'error' => 'Rollback failed',
                ];
            }
        }

        // FIX: Only transition to Failed status if we have rollbacks and the plan allows it
        // Don't try to go to Draft - that's not a valid transition from most states
        if (!empty($rolledBack) && $plan->status->canTransitionTo(PlanStatus::Failed)) {
            $plan->markFailed('Rolled back due to user request');
        }

        return RollbackResult::fromResults($rolledBack, $failed, $skipped, [
            'plan_id' => $plan->id,
            'rolled_back_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Rollback a single file execution.
     *
     * FIX: Use existing FileWriterService methods instead of non-existent ones.
     */
    public function rollbackFile(FileExecution $execution): bool
    {
        $plan = $execution->executionPlan;
        $project = $plan->project;

        if (!$execution->can_rollback) {
            return false;
        }

        $operationType = $execution->operation_type;

        try {
            if ($operationType === FileOperationType::Create) {
                // For created files, delete them (without backup since we're rolling back)
                $fullPath = $project->repo_path . '/' . $execution->file_path;
                if (file_exists($fullPath)) {
                    $result = @unlink($fullPath);
                    if (!$result) {
                        return false;
                    }
                }
                $execution->markRolledBack();
                return true;
            }

            if ($operationType === FileOperationType::Delete) {
                // For deleted files, recreate them with original content
                if ($execution->original_content === null) {
                    return false;
                }
                $result = $this->fileWriter->createFile(
                    $project,
                    $execution->file_path,
                    $execution->original_content
                );
                if ($result->success) {
                    $execution->markRolledBack();
                    return true;
                }
                return false;
            }

            // For modify/move/rename, restore original content
            if ($execution->original_content === null) {
                return false;
            }

            // Use modifyFile to overwrite with original content
            $result = $this->fileWriter->modifyFile(
                $project,
                $execution->file_path,
                $execution->original_content
            );

            if ($result->success) {
                $execution->markRolledBack();
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('ExecutionAgent: Rollback failed', [
                'execution_id' => $execution->id,
                'path' => $execution->file_path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Skip a file execution.
     */
    public function skipFile(FileExecution $execution, string $reason = 'User skipped'): void
    {
        $execution->skip($reason);
    }

    /**
     * Check if content needs AI generation.
     */
    private function needsGeneration(string $content): bool
    {
        $indicators = ['{{', '/* TODO', '// TODO', '...', 'PLACEHOLDER'];
        foreach ($indicators as $indicator) {
            if (str_contains($content, $indicator)) {
                return true;
            }
        }
        return strlen($content) < 50;
    }

    /**
     * Build system prompt for code executor using template service.
     */
    private function buildExecutorSystemPrompt(Project $project): string
    {
        $systemPrompt = $this->templateService->getAgentSystemPrompt('executor');

        return $this->templateService->render($systemPrompt, [
            'project_info' => $this->buildProjectInfo($project),
            'tech_stack' => $this->buildTechStack($project),
        ]);
    }

    /**
     * Build user prompt for creating a new file.
     */
    private function buildCreatePrompt(FileOperation $operation, ExecutionPlan $plan): string
    {
        $userPromptTemplate = $this->templateService->load('user/execution_request.md');

        return $this->templateService->render($userPromptTemplate, [
            'task_description' => $operation->description ?? 'Create new file',
            'operation_type' => 'create',
            'file_path' => $operation->path,
            'new_file_path' => '',
            'priority' => (string) $operation->priority,
            'plan_title' => $plan->title,
            'plan_summary' => $plan->description,
            'plan_approach' => $plan->plan_data['approach'] ?? '',
            'language' => $this->detectLanguage($operation->path),
            'current_file_content' => '',
            'planned_changes' => '',
            'template_content' => $operation->templateContent ?? '',
            'related_files' => '',
        ]);
    }

    /**
     * Build user prompt for modifying a file.
     */
    private function buildModifyPrompt(
        FileOperation $operation,
        string $currentContent,
        ExecutionPlan $plan
    ): string {
        $userPromptTemplate = $this->templateService->load('user/execution_request.md');

        $plannedChanges = '';
        if (!empty($operation->changes)) {
            foreach ($operation->changes as $change) {
                $plannedChanges .= "- **{$change->changeType}** in `{$change->section}`: {$change->explanation}\n";
                if ($change->content) {
                    $plannedChanges .= "  ```\n  {$change->content}\n  ```\n";
                }
            }
        }

        return $this->templateService->render($userPromptTemplate, [
            'task_description' => $operation->description ?? 'Modify existing file',
            'operation_type' => 'modify',
            'file_path' => $operation->path,
            'new_file_path' => '',
            'priority' => (string) $operation->priority,
            'plan_title' => $plan->title,
            'plan_summary' => $plan->description,
            'plan_approach' => $plan->plan_data['approach'] ?? '',
            'language' => $this->detectLanguage($operation->path),
            'current_file_content' => $currentContent,
            'planned_changes' => $plannedChanges,
            'template_content' => '',
            'related_files' => '',
        ]);
    }

    /**
     * Build project info string.
     */
    private function buildProjectInfo(Project $project): string
    {
        $stack = $project->stack_info ?? [];
        $info = "Repository: {$project->repo_full_name}\n";
        $info .= "Branch: {$project->default_branch}\n";
        $info .= "Files: " . ($project->total_files ?? 'N/A') . "\n";

        if (!empty($stack['framework'])) {
            $info .= "Framework: {$stack['framework']}";
            if (!empty($stack['framework_version'])) {
                $info .= " {$stack['framework_version']}";
            }
            $info .= "\n";
        }

        return $info;
    }

    /**
     * Build tech stack string.
     */
    private function buildTechStack(Project $project): string
    {
        $stack = $project->stack_info ?? [];
        $parts = [];

        if (!empty($stack['framework'])) {
            $parts[] = "Framework: {$stack['framework']}";
        }
        if (!empty($stack['frontend'])) {
            $parts[] = "Frontend: " . implode(', ', $stack['frontend']);
        }
        if (!empty($stack['database'])) {
            $parts[] = "Database: " . implode(', ', $stack['database']);
        }
        if (!empty($stack['css'])) {
            $parts[] = "CSS: " . implode(', ', $stack['css']);
        }

        return implode("\n", $parts);
    }

    /**
     * Detect language from file path.
     */
    private function detectLanguage(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'php' => 'php',
            'js' => 'javascript',
            'ts' => 'typescript',
            'vue' => 'vue',
            'jsx' => 'jsx',
            'tsx' => 'tsx',
            'css' => 'css',
            'scss', 'sass' => 'scss',
            'json' => 'json',
            'yaml', 'yml' => 'yaml',
            'md' => 'markdown',
            'blade.php' => 'blade',
            default => $ext,
        };
    }

    /**
     * Call Claude API.
     */
    private function callClaude(string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('execution.claude.api_key') ?: config('anthropic.api_key');
        $model = config('execution.claude.model', 'claude-sonnet-4-5-20250514');
        $maxTokens = config('execution.claude.max_tokens', 8192);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userPrompt]],
        ]);

        if (!$response->successful()) {
            throw new Exception('Claude API call failed: ' . $response->status());
        }

        return $response->json('content.0.text') ?? '';
    }

    /**
     * Extract code from Claude response.
     */
    private function extractCode(string $response): string
    {
        if (preg_match('/```(?:php|javascript|typescript|vue|html|css|json|yaml)?\s*([\s\S]*?)```/', $response, $matches)) {
            return trim($matches[1]);
        }

        return trim($response);
    }
}
