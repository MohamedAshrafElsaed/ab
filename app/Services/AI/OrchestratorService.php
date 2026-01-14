<?php

namespace App\Services\AI;

use App\DTOs\ConversationState;
use App\Enums\AgentMessageType;
use App\Enums\ConversationPhase;
use App\Enums\IntentType;
use App\Enums\PlanStatus;
use App\Events\ExecutionEvent;
use App\Events\OrchestratorEvent;
use App\Models\AgentConversation;
use App\Models\ExecutionPlan;
use App\Models\FileExecution;
use App\Models\Project;
use App\Models\User;
use App\Services\Prompts\PromptComposer;
use App\Services\Prompts\PromptTemplateService;
use Exception;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrchestratorService
{
    private array $config;

    public function __construct(
        private readonly IntentAnalyzerService $intentAnalyzer,
        private readonly ContextRetrievalService $contextRetrieval,
        private readonly PlanningAgentService $planningAgent,
        private readonly ExecutionAgentService $executionAgent,
        private readonly PromptTemplateService $templateService,
        private readonly PromptComposer $promptComposer,
    ) {
        $this->config = config('orchestrator', []);
    }

    /**
     * Start a new conversation for a project.
     */
    public function startConversation(Project $project, User $user): AgentConversation
    {
        return AgentConversation::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'active',
            'current_phase' => ConversationPhase::Intake,
            'metadata' => [
                'started_at' => now()->toIso8601String(),
                'project_name' => $project->repo_full_name,
            ],
        ]);
    }

    /**
     * Process a user message through the workflow.
     *
     * @param array<string, mixed> $options
     * @return Generator<OrchestratorEvent>
     */
    public function processMessage(
        AgentConversation $conversation,
        string $userMessage,
        array $options = []
    ): Generator {
        $project = $conversation->project;
        $startTime = microtime(true);

        try {
            $conversation->addMessage('user', $userMessage, AgentMessageType::Text);
            yield OrchestratorEvent::messageReceived($conversation->id, $userMessage);

            if (!$conversation->title) {
                $conversation->generateTitle();
            }

            yield from $this->processPhase($conversation, $project, $userMessage, $options);

        } catch (Exception $e) {
            Log::error('Orchestrator: Error processing message', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $conversation->markFailed($e->getMessage());

            yield OrchestratorEvent::error(
                $conversation->id,
                $e->getMessage(),
                $conversation->current_phase->value
            );
        }
    }

    /**
     * Process the current phase and transition as needed.
     *
     * @param array<string, mixed> $options
     * @return Generator<OrchestratorEvent>
     */
    private function processPhase(
        AgentConversation $conversation,
        Project $project,
        string $userMessage,
        array $options
    ): Generator {
        $phase = $conversation->current_phase;

        if ($phase === ConversationPhase::Intake || $phase === ConversationPhase::Clarification) {
            yield from $this->handleIntakePhase($conversation, $project, $userMessage);
            return;
        }

        if ($phase === ConversationPhase::Approval) {
            yield from $this->handleApprovalPhaseMessage($conversation, $userMessage);
            return;
        }

        if ($phase === ConversationPhase::Executing) {
            yield from $this->handleExecutingPhaseMessage($conversation, $userMessage);
            return;
        }

        if ($phase->isTerminal()) {
            $response = $this->generateSimpleResponse($conversation, $project, $userMessage);
            yield OrchestratorEvent::responseChunk($conversation->id, $response);

            $conversation->addMessage('assistant', $response, AgentMessageType::Text);
            yield OrchestratorEvent::responseComplete($conversation->id);
        }
    }

    /**
     * Handle intake phase - analyze intent and proceed.
     *
     * @return Generator<OrchestratorEvent>
     */
    private function handleIntakePhase(
        AgentConversation $conversation,
        Project $project,
        string $userMessage
    ): Generator {
        yield OrchestratorEvent::analyzingIntent($conversation->id);

        $conversationHistory = $conversation->getContextMessages(10);
        $intent = $this->intentAnalyzer->analyze(
            $project,
            $userMessage,
            $conversationHistory,
            $conversation->id,
            (string) Str::uuid()
        );

        $conversation->setCurrentIntent($intent);

        yield OrchestratorEvent::intentAnalyzed(
            $conversation->id,
            $intent->intent_type->value,
            $intent->confidence_score,
            $intent->intent_type->label()
        );

        if ($this->intentAnalyzer->needsClarification($intent)) {
            $questions = $intent->clarification_questions ?? [];
            if (empty($questions)) {
                $questions = $this->intentAnalyzer->generateClarificationQuestions($intent);
            }

            yield OrchestratorEvent::clarificationNeeded($conversation->id, $questions);

            $clarificationMessage = $this->formatClarificationQuestions($questions);
            $conversation->addMessage('assistant', $clarificationMessage, AgentMessageType::Clarification);
            $conversation->forcePhase(ConversationPhase::Clarification);

            yield OrchestratorEvent::phaseChanged(
                $conversation->id,
                ConversationPhase::Intake->value,
                ConversationPhase::Clarification->value
            );
            return;
        }

        if ($intent->intent_type === IntentType::Question) {
            yield from $this->handleQuestionIntent($conversation, $project, $intent, $userMessage);
            return;
        }

        yield from $this->proceedToDiscoveryAndPlanning($conversation, $project, $userMessage);
    }

    /**
     * Handle question intent - answer without planning.
     *
     * @return Generator<OrchestratorEvent>
     */
    private function handleQuestionIntent(
        AgentConversation $conversation,
        Project $project,
                          $intent,
        string $userMessage
    ): Generator {
        yield OrchestratorEvent::retrievingContext($conversation->id);

        $context = $this->contextRetrieval->retrieve(
            $project,
            $intent,
            $userMessage,
            ['max_chunks' => 30, 'token_budget' => 50000]
        );

        yield OrchestratorEvent::contextRetrieved(
            $conversation->id,
            $context->getFileCount(),
            $context->getChunkCount()
        );

        $response = $this->generateQuestionResponse($conversation, $project, $intent, $context, $userMessage);

        yield OrchestratorEvent::responseChunk($conversation->id, $response);
        $conversation->addMessage('assistant', $response, AgentMessageType::Text);
        yield OrchestratorEvent::responseComplete($conversation->id);
    }

    /**
     * Proceed to discovery and planning phases.
     *
     * @return Generator<OrchestratorEvent>
     */
    private function proceedToDiscoveryAndPlanning(
        AgentConversation $conversation,
        Project $project,
        string $userMessage
    ): Generator {
        $conversation->transitionTo(ConversationPhase::Discovery);
        yield OrchestratorEvent::phaseChanged(
            $conversation->id,
            ConversationPhase::Intake->value,
            ConversationPhase::Discovery->value
        );

        yield OrchestratorEvent::retrievingContext($conversation->id);

        $intent = $conversation->currentIntent;
        $context = $this->contextRetrieval->retrieve(
            $project,
            $intent,
            $userMessage,
            [
                'max_chunks' => $this->config['context']['max_chunks'] ?? 60,
                'token_budget' => $this->config['context']['token_budget'] ?? 80000,
                'include_dependencies' => true,
                'depth' => 2,
            ]
        );

        yield OrchestratorEvent::contextRetrieved(
            $conversation->id,
            $context->getFileCount(),
            $context->getChunkCount()
        );

        $conversation->updateContextSummary([
            'files_found' => $context->getFileCount(),
            'chunks_found' => $context->getChunkCount(),
            'entry_points' => array_slice($context->entryPoints, 0, 5),
        ]);

        $conversation->transitionTo(ConversationPhase::Planning);
        yield OrchestratorEvent::phaseChanged(
            $conversation->id,
            ConversationPhase::Discovery->value,
            ConversationPhase::Planning->value
        );

        yield OrchestratorEvent::generatingPlan($conversation->id);

        $plan = $this->planningAgent->generatePlan(
            $project,
            $intent,
            $userMessage,
            ['conversation_id' => $conversation->id]
        );

        $conversation->setCurrentPlan($plan);

        yield OrchestratorEvent::planGenerated(
            $conversation->id,
            $plan->id,
            $plan->title,
            $plan->estimated_files_affected
        );

        $planPreview = $this->formatPlanPreview($plan);
        $conversation->addMessage(
            'assistant',
            $planPreview,
            AgentMessageType::PlanPreview,
            ['plan_id' => $plan->id]
        );

        $conversation->transitionTo(ConversationPhase::Approval);
        yield OrchestratorEvent::phaseChanged(
            $conversation->id,
            ConversationPhase::Planning->value,
            ConversationPhase::Approval->value
        );

        yield OrchestratorEvent::awaitingApproval($conversation->id, $plan->id);
    }

    /**
     * Handle user approval of the plan.
     *
     * @return Generator<OrchestratorEvent>
     */
    public function handleApproval(
        AgentConversation $conversation,
        bool $approved,
        ?string $feedback = null
    ): Generator {
        $plan = $conversation->currentPlan;

        if (!$plan) {
            yield OrchestratorEvent::error($conversation->id, 'No plan to approve');
            return;
        }

        if ($approved) {
            $plan->approve($conversation->user_id);
            yield OrchestratorEvent::planApproved($conversation->id, $plan->id);

            $conversation->addMessage(
                'user',
                'Approved the execution plan',
                AgentMessageType::Text,
                ['action' => 'approve']
            );

            yield from $this->startExecution($conversation, $plan);
        } else {
            if ($feedback) {
                $conversation->addMessage('user', $feedback, AgentMessageType::Text);

                yield OrchestratorEvent::generatingPlan($conversation->id);

                $refinedPlan = $this->planningAgent->refinePlan($plan, $feedback);
                $conversation->setCurrentPlan($refinedPlan);

                yield OrchestratorEvent::planGenerated(
                    $conversation->id,
                    $refinedPlan->id,
                    $refinedPlan->title,
                    $refinedPlan->estimated_files_affected
                );

                $planPreview = $this->formatPlanPreview($refinedPlan);
                $conversation->addMessage(
                    'assistant',
                    $planPreview,
                    AgentMessageType::PlanPreview,
                    ['plan_id' => $refinedPlan->id]
                );

                yield OrchestratorEvent::awaitingApproval($conversation->id, $refinedPlan->id);
            } else {
                $plan->reject('User rejected without feedback');
                yield OrchestratorEvent::planRejected($conversation->id, $plan->id);

                $conversation->addMessage(
                    'assistant',
                    "I understand. The plan has been cancelled. Feel free to describe what changes you'd like to make, and I'll create a new plan.",
                    AgentMessageType::Text
                );

                $conversation->clearCurrentPlan();
                $conversation->forcePhase(ConversationPhase::Intake);
            }
        }
    }

    /**
     * Start executing an approved plan.
     *
     * @return Generator<OrchestratorEvent>
     */
    private function startExecution(AgentConversation $conversation, ExecutionPlan $plan): Generator
    {
        $conversation->transitionTo(ConversationPhase::Executing);
        yield OrchestratorEvent::phaseChanged(
            $conversation->id,
            ConversationPhase::Approval->value,
            ConversationPhase::Executing->value
        );

        $operations = $plan->getOrderedFileOperations();
        yield OrchestratorEvent::executionStarted($conversation->id, $plan->id, count($operations));

        $autoApprove = $this->config['execution']['auto_approve'] ?? false;
        $completed = 0;
        $failed = 0;
        $total = count($operations);

        foreach ($this->executionAgent->execute($plan, ['auto_approve' => $autoApprove]) as $event) {
            if ($event->type === ExecutionEvent::TYPE_FILE_COMPLETED) {
                $completed++;
                yield OrchestratorEvent::executionProgress(
                    $conversation->id,
                    $completed,
                    $total,
                    $event->data['path'] ?? null
                );

                $conversation->addMessage(
                    'assistant',
                    "Completed: {$event->data['path']}",
                    AgentMessageType::ExecutionUpdate,
                    ['progress' => $completed, 'total' => $total, 'path' => $event->data['path']]
                );
            }

            if ($event->type === ExecutionEvent::TYPE_FILE_FAILED) {
                $failed++;
                $conversation->addMessage(
                    'assistant',
                    "Failed: {$event->data['path']} - {$event->data['error']}",
                    AgentMessageType::Error,
                    ['path' => $event->data['path'], 'error' => $event->data['error']]
                );
            }

            if ($event->type === ExecutionEvent::TYPE_AWAITING_APPROVAL) {
                yield OrchestratorEvent::fileApprovalNeeded(
                    $conversation->id,
                    $event->data['execution_id'],
                    $event->data['path'],
                    $event->data['diff'] ?? null
                );
                return;
            }
        }

        yield OrchestratorEvent::executionCompleted($conversation->id, $completed, $failed);

        $plan->refresh();
        if ($plan->status === PlanStatus::Completed) {
            $conversation->markCompleted();

            $successMessage = "All {$completed} file(s) were updated successfully. The changes have been applied to your project.";
            $conversation->addMessage('assistant', $successMessage, AgentMessageType::Text);

            yield OrchestratorEvent::responseChunk($conversation->id, $successMessage);
            yield OrchestratorEvent::responseComplete($conversation->id);
        } else {
            $conversation->markFailed("Execution completed with {$failed} failure(s)");

            $failMessage = "Execution completed with {$failed} failure(s) out of {$total} file(s).";
            $conversation->addMessage('assistant', $failMessage, AgentMessageType::Error);

            yield OrchestratorEvent::error($conversation->id, $failMessage, 'executing');
        }
    }

    /**
     * Handle file approval during execution.
     *
     * @return Generator<OrchestratorEvent>
     */
    public function handleFileApproval(
        AgentConversation $conversation,
        string $executionId,
        bool $approved
    ): Generator {
        $execution = FileExecution::find($executionId);

        if (!$execution) {
            yield OrchestratorEvent::error($conversation->id, 'File execution not found');
            return;
        }

        if ($approved) {
            $execution->approve();

            foreach ($this->executionAgent->continueExecution($execution) as $event) {
                if ($event->type === ExecutionEvent::TYPE_FILE_COMPLETED) {
                    yield OrchestratorEvent::executionProgress(
                        $conversation->id,
                        $event->data['index'] + 1,
                        $conversation->currentPlan?->estimated_files_affected ?? 0,
                        $event->data['path']
                    );
                }

                if ($event->type === ExecutionEvent::TYPE_AWAITING_APPROVAL) {
                    yield OrchestratorEvent::fileApprovalNeeded(
                        $conversation->id,
                        $event->data['execution_id'],
                        $event->data['path'],
                        $event->data['diff'] ?? null
                    );
                    return;
                }

                if ($event->type === ExecutionEvent::TYPE_COMPLETED) {
                    yield OrchestratorEvent::executionCompleted(
                        $conversation->id,
                        $event->data['files_completed'],
                        $event->data['files_failed']
                    );
                    $conversation->markCompleted();
                }
            }
        } else {
            $this->executionAgent->skipFile($execution, 'User skipped');

            yield OrchestratorEvent::executionProgress(
                $conversation->id,
                $execution->file_operation_index + 1,
                $conversation->currentPlan?->estimated_files_affected ?? 0,
                $execution->file_path . ' (skipped)'
            );
        }
    }

    /**
     * Cancel the current operation.
     *
     * @return Generator<OrchestratorEvent>
     */
    public function cancel(AgentConversation $conversation): Generator
    {
        $phase = $conversation->current_phase;

        if ($phase === ConversationPhase::Executing) {
            $plan = $conversation->currentPlan;
            if ($plan) {
                $this->executionAgent->rollbackPlan($plan);
            }
        }

        $conversation->update(['status' => 'paused']);
        yield OrchestratorEvent::cancelled($conversation->id, 'User cancelled');

        $conversation->addMessage(
            'assistant',
            'Operation cancelled. You can continue whenever you\'re ready.',
            AgentMessageType::SystemNotice
        );
    }

    /**
     * Get the current state of a conversation.
     */
    public function getState(AgentConversation $conversation): ConversationState
    {
        return ConversationState::fromConversation($conversation);
    }

    /**
     * Handle messages during approval phase.
     *
     * @return Generator<OrchestratorEvent>
     */
    private function handleApprovalPhaseMessage(
        AgentConversation $conversation,
        string $userMessage
    ): Generator {
        $lowerMessage = strtolower(trim($userMessage));

        if (in_array($lowerMessage, ['yes', 'approve', 'ok', 'go', 'proceed', 'execute', 'do it'])) {
            yield from $this->handleApproval($conversation, true);
            return;
        }

        if (in_array($lowerMessage, ['no', 'cancel', 'stop', 'reject'])) {
            yield from $this->handleApproval($conversation, false);
            return;
        }

        yield from $this->handleApproval($conversation, false, $userMessage);
    }

    /**
     * Handle messages during executing phase.
     *
     * @return Generator<OrchestratorEvent>
     */
    private function handleExecutingPhaseMessage(
        AgentConversation $conversation,
        string $userMessage
    ): Generator {
        $response = "Execution is in progress. Please wait for the current operation to complete, or use the cancel button to stop.";
        yield OrchestratorEvent::responseChunk($conversation->id, $response);
        $conversation->addMessage('assistant', $response, AgentMessageType::Text);
        yield OrchestratorEvent::responseComplete($conversation->id);
    }

    /**
     * Generate a simple response for terminal phases.
     */
    private function generateSimpleResponse(
        AgentConversation $conversation,
        Project $project,
        string $userMessage
    ): string {
        if ($conversation->current_phase === ConversationPhase::Completed) {
            return "The previous task has been completed. Would you like to start a new task? Just describe what you'd like to do.";
        }

        if ($conversation->current_phase === ConversationPhase::Failed) {
            return "The previous operation encountered an error. Would you like to try again or start something new?";
        }

        return "I'm ready to help. Please describe what you'd like to do with this project.";
    }

    /**
     * Generate a response for question intents.
     */
    private function generateQuestionResponse(
        AgentConversation $conversation,
        Project $project,
                          $intent,
                          $context,
        string $userMessage
    ): string {
        $chunks = $context->chunks->take(20);

        $composed = $this->promptComposer->composeQuick(
            $project,
            $userMessage,
            $chunks->toArray()
        );

        try {
            $config = $this->templateService->getAgentConfig('orchestrator');

            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $config['model'] ?? 'claude-sonnet-4-5-20250514',
                'max_tokens' => $config['max_tokens'] ?? 4096,
                'temperature' => $config['temperature'] ?? 0.3,
                'system' => $composed->systemPrompt,
                'messages' => $composed->toMessages()['messages'],
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text') ?? 'I found relevant code but could not generate a response.';
            }

            Log::warning('Orchestrator: Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 'I encountered an issue generating a response. Please try again.';

        } catch (Exception $e) {
            Log::error('Orchestrator: Question response error', [
                'error' => $e->getMessage(),
            ]);

            return 'I encountered an error while processing your question. Please try again.';
        }
    }

    /**
     * @param array<string> $questions
     */
    private function formatClarificationQuestions(array $questions): string
    {
        if (empty($questions)) {
            return "Could you provide more details about what you'd like to do?";
        }

        $formatted = "I need a bit more information to help you:\n\n";
        foreach ($questions as $i => $question) {
            $num = $i + 1;
            $formatted .= "{$num}. {$question}\n";
        }

        return $formatted;
    }

    private function formatPlanPreview(ExecutionPlan $plan): string
    {
        $output = "## {$plan->title}\n\n";
        $output .= "{$plan->description}\n\n";

        $output .= "### Files to be modified\n\n";

        $operations = $plan->file_operations ?? [];
        foreach ($operations as $op) {
            $type = strtoupper($op['type'] ?? 'unknown');
            $path = $op['path'] ?? 'unknown';
            $desc = $op['description'] ?? '';
            $output .= "- **[{$type}]** `{$path}`";
            if ($desc) {
                $output .= " - {$desc}";
            }
            $output .= "\n";
        }

        $output .= "\n### Estimated Impact\n\n";
        $output .= "- **Complexity:** {$plan->estimated_complexity?->label()}\n";
        $output .= "- **Files affected:** {$plan->estimated_files_affected}\n";

        if (!empty($plan->risks)) {
            $output .= "\n### Risks\n\n";
            foreach ($plan->risks as $risk) {
                $level = strtoupper($risk['level'] ?? 'unknown');
                $desc = $risk['description'] ?? '';
                $output .= "- **[{$level}]** {$desc}\n";
            }
        }

        $output .= "\n---\n\n";
        $output .= "**Would you like me to proceed with these changes?** Reply with 'yes' to approve or provide feedback to refine the plan.";

        return $output;
    }
}
