<?php

namespace App\Services\AI;

use App\DTOs\ComposedPrompt;
use App\DTOs\FileOperation;
use App\DTOs\RetrievalResult;
use App\DTOs\RiskAssessment;
use App\DTOs\ValidationResult;
use App\Enums\ComplexityLevel;
use App\Enums\FileOperationType;
use App\Enums\PlanStatus;
use App\Models\ExecutionPlan;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Services\Prompts\PromptTemplateService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlanningAgentService
{
    private array $config;

    public function __construct(
        private readonly ContextRetrievalService $contextRetrieval,
        private readonly PromptTemplateService $templateService,
    ) {
        $this->config = config('planning', []);
    }

    /**
     * Generate an execution plan for the user's request.
     *
     * @param array<string, mixed> $options
     */
    public function generatePlan(
        Project $project,
        IntentAnalysis $intent,
        string $userMessage,
        array $options = []
    ): ExecutionPlan {
        $startTime = microtime(true);
        $conversationId = $options['conversation_id'] ?? (string) Str::uuid();

        try {
            Log::info('PlanningAgent: Starting plan generation', [
                'project_id' => $project->id,
                'intent_type' => $intent->intent_type->value,
                'message' => substr($userMessage, 0, 100),
            ]);

            // Step 1: Retrieve relevant context
            $context = $this->contextRetrieval->retrieve(
                $project,
                $intent,
                $userMessage,
                [
                    'max_chunks' => $this->config['context']['max_chunks'] ?? 60,
                    'token_budget' => $this->config['context']['token_budget'] ?? 80000,
                    'include_dependencies' => true,
                    'depth' => 3,
                ]
            );

            Log::debug('PlanningAgent: Context retrieved', [
                'chunks' => $context->getChunkCount(),
                'files' => $context->getFileCount(),
                'estimated_tokens' => $context->getTotalTokenEstimate(),
            ]);

            // Step 2: Build prompts using PromptTemplateService
            $composedPrompt = $this->buildPrompts($project, $intent, $userMessage, $context);

            // Step 3: Call Claude with extended thinking
            $response = $this->callClaudeWithPrompt($composedPrompt);

            // Step 4: Parse the response
            $planData = $this->parsePlanResponse($response);

            // Step 5: Validate and parse file operations
            $fileOperations = $this->parseFileOperations($planData['file_operations'] ?? []);

            // Step 6: Calculate complexity and risk
            $complexity = $this->calculateComplexity($fileOperations, $intent);
            $risks = $this->extractRisks($planData);

            // Step 7: Create the execution plan
            $plan = ExecutionPlan::create([
                'project_id' => $project->id,
                'conversation_id' => $conversationId,
                'intent_analysis_id' => $intent->id,
                'status' => PlanStatus::Draft,
                'title' => $planData['title'] ?? $this->generateTitle($userMessage),
                'description' => $planData['summary'] ?? $planData['description'] ?? '',
                'plan_data' => [
                    'approach' => $planData['approach'] ?? '',
                    'testing_notes' => $planData['testing_notes'] ?? '',
                    'estimated_time' => $planData['estimated_time'] ?? '',
                    'manual_steps' => $planData['manual_steps'] ?? [],
                ],
                'file_operations' => array_map(fn(FileOperation $op) => $op->toArray(), $fileOperations),
                'estimated_complexity' => $complexity,
                'estimated_files_affected' => count($fileOperations),
                'risks' => $risks,
                'prerequisites' => $planData['prerequisites'] ?? [],
                'metadata' => [
                    'generation_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'model' => $this->getAgentConfig()['model'] ?? 'claude-sonnet-4-5-20250514',
                    'context_chunks' => $context->getChunkCount(),
                    'context_tokens' => $context->getTotalTokenEstimate(),
                    'thinking_tokens' => $response['thinking_tokens'] ?? 0,
                ],
            ]);

            // Step 8: Submit for review
            $plan->submitForReview();

            Log::info('PlanningAgent: Plan generated', [
                'plan_id' => $plan->id,
                'project_id' => $project->id,
                'files_affected' => count($fileOperations),
                'complexity' => $complexity->value,
            ]);

            return $plan;

        } catch (Exception $e) {
            Log::error('PlanningAgent: Failed to generate plan', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->createFailedPlan($project, $intent, $conversationId, $e->getMessage());
        }
    }

    /**
     * Refine an existing plan based on user feedback.
     */
    public function refinePlan(ExecutionPlan $plan, string $userFeedback): ExecutionPlan
    {
        $startTime = microtime(true);

        try {
            $plan->addUserFeedback($userFeedback);
            $plan->revertToDraft();

            $project = $plan->project;
            $intent = $plan->intentAnalysis;

            if (!$intent) {
                throw new Exception('Original intent analysis not found');
            }

            // Retrieve additional context based on feedback
            $context = $this->contextRetrieval->retrieve(
                $project,
                $intent,
                $userFeedback,
                ['max_chunks' => 40, 'token_budget' => 60000]
            );

            // Build refinement prompt
            $composedPrompt = $this->buildRefinementPrompt($plan, $userFeedback, $context);

            // Call Claude for refinement
            $response = $this->callClaudeWithPrompt($composedPrompt);
            $planData = $this->parsePlanResponse($response);

            // Update the plan
            $fileOperations = $this->parseFileOperations($planData['file_operations'] ?? []);

            $plan->update([
                'title' => $planData['title'] ?? $plan->title,
                'description' => $planData['summary'] ?? $plan->description,
                'plan_data' => array_merge($plan->plan_data ?? [], [
                    'approach' => $planData['approach'] ?? $plan->plan_data['approach'] ?? '',
                    'testing_notes' => $planData['testing_notes'] ?? '',
                    'refinement_count' => ($plan->plan_data['refinement_count'] ?? 0) + 1,
                ]),
                'file_operations' => array_map(fn(FileOperation $op) => $op->toArray(), $fileOperations),
                'estimated_files_affected' => count($fileOperations),
                'risks' => $this->extractRisks($planData),
                'prerequisites' => $planData['prerequisites'] ?? $plan->prerequisites,
                'metadata' => array_merge($plan->metadata ?? [], [
                    'last_refined_at' => now()->toIso8601String(),
                    'refinement_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]),
            ]);

            $plan->submitForReview();

            Log::info('PlanningAgent: Plan refined', [
                'plan_id' => $plan->id,
                'files_affected' => count($fileOperations),
            ]);

            return $plan->fresh();

        } catch (Exception $e) {
            Log::error('PlanningAgent: Failed to refine plan', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate that a plan is complete and executable.
     */
    public function validatePlan(ExecutionPlan $plan): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $missingFiles = [];
        $circularDeps = [];

        $operations = $plan->file_operations ?? [];

        if (empty($operations)) {
            $errors[] = 'Plan has no file operations';
            return ValidationResult::invalid($errors);
        }

        // Build path index
        $pathsInPlan = [];
        $createPaths = [];
        foreach ($operations as $op) {
            $pathsInPlan[$op['path']] = $op['type'];
            if ($op['type'] === 'create') {
                $createPaths[] = $op['path'];
            }
        }

        // Validate each operation
        foreach ($operations as $index => $op) {
            $path = $op['path'];
            $type = $op['type'];

            if (empty($path)) {
                $errors[] = "Operation #{$index}: missing path";
            }

            if (in_array($type, ['modify', 'delete', 'rename', 'move'])) {
                if (!$this->fileExistsInProject($plan->project, $path)) {
                    if (!in_array($path, $createPaths)) {
                        $missingFiles[] = $path;
                    }
                }
            }

            if ($type === 'create' && empty($op['template_content'])) {
                $errors[] = "Operation #{$index}: create operation missing content for {$path}";
            }

            if ($type === 'modify' && empty($op['changes'])) {
                $errors[] = "Operation #{$index}: modify operation missing changes for {$path}";
            }

            foreach ($op['dependencies'] ?? [] as $dep) {
                if (!isset($pathsInPlan[$dep]) && !$this->fileExistsInProject($plan->project, $dep)) {
                    $warnings[] = "Operation for {$path} depends on {$dep} which is not in plan";
                }
            }
        }

        $circularDeps = $this->detectCircularDependencies($operations);

        $priorities = array_column($operations, 'priority');
        if (count(array_unique($priorities)) === 1 && count($priorities) > 1) {
            $warnings[] = 'All operations have the same priority - execution order may be undefined';
        }

        return new ValidationResult(
            isValid: empty($errors) && empty($missingFiles) && empty($circularDeps),
            errors: $errors,
            warnings: $warnings,
            missingFiles: $missingFiles,
            circularDependencies: $circularDeps,
        );
    }

    /**
     * Check if any files in the plan are missing from context.
     *
     * @return array<string>
     */
    public function identifyMissingContext(ExecutionPlan $plan, RetrievalResult $context): array
    {
        $contextFiles = $context->getFileList();
        $missingContext = [];

        foreach ($plan->file_operations ?? [] as $op) {
            $type = $op['type'];

            if (in_array($type, ['modify', 'delete', 'rename', 'move'])) {
                $path = $op['path'];
                if (!in_array($path, $contextFiles)) {
                    $missingContext[] = $path;
                }
            }

            foreach ($op['dependencies'] ?? [] as $dep) {
                if (!in_array($dep, $contextFiles) && !in_array($dep, $missingContext)) {
                    $missingContext[] = $dep;
                }
            }
        }

        return array_unique($missingContext);
    }

    /**
     * Estimate the complexity and risk of a plan.
     */
    public function assessRisk(ExecutionPlan $plan): RiskAssessment
    {
        $risks = [];
        $prerequisites = [];
        $manualSteps = [];

        $operations = $plan->file_operations ?? [];
        $deleteCount = 0;
        $modifyCount = 0;

        foreach ($operations as $op) {
            $type = $op['type'];

            if ($type === 'delete') {
                $deleteCount++;
                $risks[] = [
                    'level' => 'medium',
                    'description' => "Deleting file: {$op['path']}",
                    'mitigation' => 'Ensure file is not referenced elsewhere',
                ];
            }

            if ($type === 'modify') {
                $modifyCount++;
            }
        }

        if ($deleteCount >= 3) {
            $risks[] = [
                'level' => 'high',
                'description' => "Multiple file deletions ({$deleteCount} files)",
                'mitigation' => 'Review each deletion carefully',
            ];
        }

        if ($modifyCount >= 10) {
            $risks[] = [
                'level' => 'medium',
                'description' => "Large number of file modifications ({$modifyCount} files)",
                'mitigation' => 'Test thoroughly after changes',
            ];
        }

        foreach ($plan->risks ?? [] as $risk) {
            $risks[] = $risk;
        }

        $prerequisites = $plan->prerequisites ?? [];

        if (!empty($plan->plan_data['manual_steps'])) {
            $manualSteps = $plan->plan_data['manual_steps'];
        }

        return RiskAssessment::calculate($risks, $prerequisites, $manualSteps);
    }

    // =========================================================================
    // Private Methods - Prompt Building
    // =========================================================================

    /**
     * Build prompts using PromptTemplateService.
     */
    private function buildPrompts(
        Project $project,
        IntentAnalysis $intent,
        string $userMessage,
        RetrievalResult $context
    ): ComposedPrompt {
        // Build system prompt from template
        $systemPrompt = $this->templateService->getAgentSystemPrompt('planner');
        $systemPrompt = $this->templateService->render($systemPrompt, [
            'project_info' => $this->buildProjectInfo($project),
            'tech_stack' => $this->buildTechStack($project),
        ]);

        // Build user prompt from template
        $userPromptTemplate = $this->templateService->load('user/planning_request.md');
        $userPrompt = $this->templateService->render($userPromptTemplate, [
            'user_request' => $userMessage,
            'intent_type' => $intent->intent_type->label(),
            'intent_confidence' => number_format($intent->confidence_score, 2),
            'intent_complexity' => $intent->complexity_estimate->label(),
            'intent_domain' => $intent->domain_classification['primary'] ?? 'general',
            'mentioned_files' => implode(', ', $intent->extracted_entities['files'] ?? []),
            'mentioned_symbols' => implode(', ', $intent->extracted_entities['symbols'] ?? []),
            'codebase_context' => $context->toPromptContext(),
        ]);

        return new ComposedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            metadata: [
                'agent' => 'planner',
                'intent_type' => $intent->intent_type->value,
                'project_id' => $project->id,
                'context_chunks' => $context->getChunkCount(),
            ],
        );
    }

    /**
     * Build refinement prompt.
     */
    private function buildRefinementPrompt(
        ExecutionPlan $plan,
        string $feedback,
        RetrievalResult $context
    ): ComposedPrompt {
        $project = $plan->project;

        $systemPrompt = $this->templateService->getAgentSystemPrompt('planner');
        $systemPrompt = $this->templateService->render($systemPrompt, [
            'project_info' => $this->buildProjectInfo($project),
            'tech_stack' => $this->buildTechStack($project),
        ]);

        $userPrompt = "## Refinement Request\n\n";
        $userPrompt .= "The user has provided feedback on the existing plan:\n\n";
        $userPrompt .= "### User Feedback\n{$feedback}\n\n";
        $userPrompt .= "### Current Plan\n";
        $userPrompt .= "Title: {$plan->title}\n";
        $userPrompt .= "Description: {$plan->description}\n\n";
        $userPrompt .= "### Current File Operations\n```json\n";
        $userPrompt .= json_encode($plan->file_operations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $userPrompt .= "\n```\n\n";
        $userPrompt .= "### Additional Context\n";
        $userPrompt .= $context->toPromptContext();
        $userPrompt .= "\n\nPlease update the plan based on the user's feedback. ";
        $userPrompt .= "Respond with the same JSON format as the original plan.";

        return new ComposedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            metadata: ['agent' => 'planner', 'mode' => 'refinement'],
        );
    }

    private function buildProjectInfo(Project $project): string
    {
        $stack = $project->stack_info ?? [];
        $info = "Repository: {$project->repo_full_name}\n";
        $info .= "Branch: {$project->default_branch}\n";
        $info .= "Files: " . ($project->total_files ?? 'N/A') . "\n";
        $info .= "Lines: " . ($project->total_lines ?? 'N/A') . "\n";

        if (!empty($stack['framework'])) {
            $info .= "Framework: {$stack['framework']}";
            if (!empty($stack['framework_version'])) {
                $info .= " {$stack['framework_version']}";
            }
            $info .= "\n";
        }

        return $info;
    }

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
        if (!empty($stack['testing'])) {
            $parts[] = "Testing: " . implode(', ', $stack['testing']);
        }

        return implode("\n", $parts);
    }

    // =========================================================================
    // Private Methods - Claude API
    // =========================================================================

    /**
     * Get agent configuration from PromptTemplateService.
     *
     * @return array<string, mixed>
     */
    private function getAgentConfig(): array
    {
        return $this->templateService->getAgentConfig('planner');
    }

    /**
     * Call Claude API with extended thinking.
     *
     * @return array<string, mixed>
     */
    private function callClaudeWithPrompt(ComposedPrompt $prompt): array
    {
        $agentConfig = $this->getAgentConfig();
        $apiKey = config('planning.claude.api_key') ?: config('anthropic.api_key');
        $model = $agentConfig['model'] ?? $this->config['claude']['model'] ?? 'claude-sonnet-4-5-20250514';
        $maxTokens = $agentConfig['max_tokens'] ?? $this->config['claude']['max_tokens'] ?? 8192;
        $thinkingBudget = $agentConfig['thinking_budget'] ?? $this->config['claude']['thinking_budget'] ?? 16000;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(180)->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => $thinkingBudget,
            ],
            'system' => $prompt->systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $prompt->userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('PlanningAgent: Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Claude API call failed: ' . $response->status());
        }

        $content = $response->json('content', []);
        $thinkingTokens = 0;
        $textContent = '';

        foreach ($content as $block) {
            if ($block['type'] === 'thinking') {
                $thinkingTokens += strlen($block['thinking'] ?? '') / 4;
            } elseif ($block['type'] === 'text') {
                $textContent .= $block['text'];
            }
        }

        return [
            'text' => $textContent,
            'thinking_tokens' => (int) $thinkingTokens,
            'usage' => $response->json('usage', []),
        ];
    }

    // =========================================================================
    // Private Methods - Response Parsing
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function parsePlanResponse(array $response): array
    {
        $text = $response['text'] ?? '';

        // Extract JSON from markdown code blocks
        if (preg_match('/```json\s*([\s\S]*?)```/', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
            $parsed = json_decode($jsonStr, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        Log::warning('PlanningAgent: Failed to parse JSON response', [
            'text' => substr($text, 0, 500),
        ]);

        return [
            'title' => 'Plan Generated',
            'summary' => $text,
            'file_operations' => [],
        ];
    }

    /**
     * @param array<array<string, mixed>> $rawOperations
     * @return array<FileOperation>
     */
    private function parseFileOperations(array $rawOperations): array
    {
        $operations = [];

        foreach ($rawOperations as $op) {
            try {
                $operations[] = FileOperation::fromArray($op);
            } catch (Exception $e) {
                Log::warning('PlanningAgent: Invalid file operation', [
                    'operation' => $op,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $operations;
    }

    // =========================================================================
    // Private Methods - Helpers
    // =========================================================================

    /**
     * @param array<FileOperation> $operations
     */
    private function calculateComplexity(array $operations, IntentAnalysis $intent): ComplexityLevel
    {
        $fileCount = count($operations);
        $totalLines = array_sum(array_map(fn($op) => $op->getEstimatedLinesAffected(), $operations));
        $hasDeletes = count(array_filter($operations, fn($op) => $op->type === FileOperationType::Delete)) > 0;

        $complexity = match (true) {
            $fileCount >= 15 => ComplexityLevel::Major,
            $fileCount >= 8 => ComplexityLevel::Complex,
            $fileCount >= 4 => ComplexityLevel::Medium,
            $fileCount >= 2 => ComplexityLevel::Simple,
            default => ComplexityLevel::Trivial,
        };

        if ($totalLines > 500 && $complexity->weight() < ComplexityLevel::Complex->weight()) {
            $complexity = ComplexityLevel::Complex;
        }

        if ($hasDeletes && $complexity->weight() < ComplexityLevel::Medium->weight()) {
            $complexity = ComplexityLevel::Medium;
        }

        $intentComplexity = $intent->complexity_estimate;
        if ($intentComplexity->weight() > $complexity->weight()) {
            return $intentComplexity;
        }

        return $complexity;
    }

    /**
     * @return array<array{level: string, description: string, mitigation: ?string}>
     */
    private function extractRisks(array $planData): array
    {
        $risks = [];

        foreach ($planData['risks'] ?? [] as $risk) {
            $risks[] = [
                'level' => $risk['level'] ?? 'low',
                'description' => $risk['description'] ?? '',
                'mitigation' => $risk['mitigation'] ?? null,
            ];
        }

        return $risks;
    }

    private function generateTitle(string $message): string
    {
        $message = trim($message);
        if (strlen($message) <= 60) {
            return $message;
        }

        return substr($message, 0, 57) . '...';
    }

    private function fileExistsInProject(Project $project, string $path): bool
    {
        return $project->files()->where('path', $path)->exists();
    }

    private function createFailedPlan(
        Project $project,
        IntentAnalysis $intent,
        string $conversationId,
        string $error
    ): ExecutionPlan {
        return ExecutionPlan::create([
            'project_id' => $project->id,
            'conversation_id' => $conversationId,
            'intent_analysis_id' => $intent->id,
            'status' => PlanStatus::Draft,
            'title' => 'Plan Generation Failed',
            'description' => 'An error occurred while generating the plan: ' . $error,
            'plan_data' => ['error' => $error],
            'file_operations' => [],
            'estimated_complexity' => ComplexityLevel::Medium,
            'estimated_files_affected' => 0,
            'metadata' => [
                'error' => $error,
                'failed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @param array<array<string, mixed>> $operations
     * @return array<array{from: string, to: string, cycle: array<string>}>
     */
    private function detectCircularDependencies(array $operations): array
    {
        $graph = [];
        foreach ($operations as $op) {
            $path = $op['path'];
            $graph[$path] = $op['dependencies'] ?? [];
        }

        $circular = [];
        $visited = [];
        $stack = [];

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $this->dfsDetectCycle($node, $graph, $visited, $stack, $circular);
            }
        }

        return $circular;
    }

    /**
     * @param array<string, array<string>> $graph
     * @param array<string, bool> $visited
     * @param array<string, bool> $stack
     * @param array<array{from: string, to: string, cycle: array<string>}> $circular
     */
    private function dfsDetectCycle(
        string $node,
        array $graph,
        array &$visited,
        array &$stack,
        array &$circular
    ): void {
        $visited[$node] = true;
        $stack[$node] = true;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $this->dfsDetectCycle($neighbor, $graph, $visited, $stack, $circular);
            } elseif (isset($stack[$neighbor])) {
                $circular[] = [
                    'from' => $node,
                    'to' => $neighbor,
                    'cycle' => array_keys($stack),
                ];
            }
        }

        unset($stack[$node]);
    }
}
