<?php

namespace Tests\Feature;

use App\Enums\AgentMessageType;
use App\Enums\ComplexityLevel;
use App\Enums\ConversationPhase;
use App\Enums\IntentType;
use App\Enums\PlanStatus;
use App\Events\OrchestratorEvent;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Models\ExecutionPlan;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Models\User;
use App\Services\AI\ContextRetrievalService;
use App\Services\AI\ExecutionAgentService;
use App\Services\AI\IntentAnalyzerService;
use App\Services\AI\OrchestratorService;
use App\Services\AI\PlanningAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrchestratorService $orchestrator;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'repo_full_name' => 'test/project',
            'total_files' => 100,
            'stack_info' => [
                'framework' => 'laravel',
                'frontend' => ['vue'],
            ],
        ]);

        $this->orchestrator = app(OrchestratorService::class);
    }

    public function test_starts_new_conversation(): void
    {
        $conversation = $this->orchestrator->startConversation($this->project, $this->user);

        $this->assertInstanceOf(AgentConversation::class, $conversation);
        $this->assertEquals($this->project->id, $conversation->project_id);
        $this->assertEquals($this->user->id, $conversation->user_id);
        $this->assertEquals('active', $conversation->status);
        $this->assertEquals(ConversationPhase::Intake, $conversation->current_phase);
    }

    public function test_conversation_generates_title_from_first_message(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->intake()
            ->create(['title' => null]);

        $conversation->addMessage('user', 'Add a new user registration feature with email verification');
        $title = $conversation->generateTitle();

        $this->assertNotEmpty($title);
        $this->assertStringContainsString('user registration', strtolower($title));
    }

    public function test_gets_conversation_state(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->approval()
            ->create();

        $state = $this->orchestrator->getState($conversation);

        $this->assertEquals($conversation->id, $state->conversationId);
        $this->assertEquals(ConversationPhase::Approval, $state->phase);
        $this->assertTrue($state->isAwaitingApproval());
        $this->assertContains('approve_plan', $state->availableActions);
        $this->assertContains('reject_plan', $state->availableActions);
    }

    public function test_conversation_phase_transitions(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->intake()
            ->create();

        $this->assertTrue($conversation->current_phase->canTransitionTo(ConversationPhase::Discovery));
        $this->assertFalse($conversation->current_phase->canTransitionTo(ConversationPhase::Executing));

        $conversation->transitionTo(ConversationPhase::Discovery);
        $this->assertEquals(ConversationPhase::Discovery, $conversation->fresh()->current_phase);
    }

    public function test_conversation_phase_invalid_transition_throws(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->intake()
            ->create();

        $this->expectException(\InvalidArgumentException::class);
        $conversation->transitionTo(ConversationPhase::Completed);
    }

    public function test_adds_message_to_conversation(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $message = $conversation->addMessage(
            'user',
            'Help me add authentication',
            AgentMessageType::Text,
            [],
            ['source' => 'test']
        );

        $this->assertInstanceOf(AgentMessage::class, $message);
        $this->assertEquals('user', $message->role);
        $this->assertEquals('Help me add authentication', $message->content);
        $this->assertEquals(AgentMessageType::Text, $message->message_type);
    }

    public function test_gets_context_messages_for_conversation(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $conversation->addMessage('user', 'First message');
        $conversation->addMessage('assistant', 'First response');
        $conversation->addMessage('user', 'Second message');

        $context = $conversation->getContextMessages(10);

        $this->assertCount(3, $context);
        $this->assertEquals('user', $context[0]['role']);
        $this->assertEquals('First message', $context[0]['content']);
    }

    public function test_marks_conversation_completed(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->executing()
            ->create();

        $conversation->forcePhase(ConversationPhase::Completed);
        $conversation->markCompleted();

        $conversation->refresh();
        $this->assertEquals('completed', $conversation->status);
        $this->assertEquals(ConversationPhase::Completed, $conversation->current_phase);
        $this->assertArrayHasKey('completed_at', $conversation->metadata);
    }

    public function test_marks_conversation_failed(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->executing()
            ->create();

        $conversation->markFailed('Test error');

        $conversation->refresh();
        $this->assertEquals('failed', $conversation->status);
        $this->assertEquals(ConversationPhase::Failed, $conversation->current_phase);
        $this->assertEquals('Test error', $conversation->metadata['last_error']);
    }

    public function test_pauses_and_resumes_conversation(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->planning()
            ->create();

        $conversation->pause();
        $this->assertEquals('paused', $conversation->fresh()->status);

        $conversation->resume();
        $this->assertEquals('active', $conversation->fresh()->status);
    }

    public function test_conversation_state_includes_available_actions(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->intake()
            ->create();

        $state = $this->orchestrator->getState($conversation);

        $this->assertContains('send_message', $state->availableActions);
        $this->assertContains('cancel', $state->availableActions);
        $this->assertNotContains('approve_plan', $state->availableActions);
    }

    public function test_completed_conversation_shows_new_conversation_action(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->completed()
            ->create();

        $state = $this->orchestrator->getState($conversation);

        $this->assertContains('new_conversation', $state->availableActions);
        $this->assertNotContains('send_message', $state->availableActions);
    }

    public function test_message_type_enum_properties(): void
    {
        $type = AgentMessageType::PlanPreview;

        $this->assertEquals('Execution Plan', $type->label());
        $this->assertEquals('clipboard-list', $type->icon());
        $this->assertEquals('purple', $type->color());
        $this->assertTrue($type->hasAttachments());
        $this->assertFalse($type->requiresAction());
    }

    public function test_message_type_approval_requires_action(): void
    {
        $type = AgentMessageType::ApprovalRequest;

        $this->assertTrue($type->requiresAction());
    }

    public function test_conversation_phase_enum_properties(): void
    {
        $phase = ConversationPhase::Planning;

        $this->assertEquals('Creating Plan', $phase->label());
        $this->assertEquals('file-text', $phase->icon());
        $this->assertEquals('purple', $phase->color());
        $this->assertTrue($phase->isActive());
        $this->assertFalse($phase->isTerminal());
        $this->assertFalse($phase->requiresUserAction());
    }

    public function test_approval_phase_requires_user_action(): void
    {
        $phase = ConversationPhase::Approval;

        $this->assertTrue($phase->requiresUserAction());
    }

    public function test_terminal_phases_are_marked_correctly(): void
    {
        $this->assertTrue(ConversationPhase::Completed->isTerminal());
        $this->assertTrue(ConversationPhase::Failed->isTerminal());
        $this->assertFalse(ConversationPhase::Executing->isTerminal());
    }

    public function test_message_formats_plan_preview_content(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $message = $conversation->addMessage(
            'assistant',
            'Plan preview content',
            AgentMessageType::PlanPreview,
            ['plan_id' => 'test-plan-123']
        );

        $formatted = $message->formatted_content;
        $this->assertStringContainsString('Plan preview content', $formatted);
        $this->assertStringContainsString('test-plan-123', $formatted);
    }

    public function test_message_formats_diff_content(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $message = $conversation->addMessage(
            'assistant',
            'File changes',
            AgentMessageType::FileDiff,
            ['path' => 'app/Test.php', 'diff' => '--- a\n+++ b']
        );

        $formatted = $message->formatted_content;
        $this->assertStringContainsString('app/Test.php', $formatted);
        $this->assertStringContainsString('```diff', $formatted);
    }

    public function test_message_to_api_format(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $message = $conversation->addMessage('user', 'Test message', AgentMessageType::Text);
        $api = $message->toApiFormat();

        $this->assertArrayHasKey('id', $api);
        $this->assertArrayHasKey('role', $api);
        $this->assertArrayHasKey('content', $api);
        $this->assertArrayHasKey('type', $api);
        $this->assertEquals('user', $api['role']);
        $this->assertEquals('text', $api['type']['value']);
    }

    public function test_conversation_to_state_array(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->planning()
            ->create(['title' => 'Test Conversation']);

        $state = $conversation->toStateArray();

        $this->assertEquals($conversation->id, $state['id']);
        $this->assertEquals('Test Conversation', $state['title']);
        $this->assertEquals('active', $state['status']);
        $this->assertEquals('planning', $state['phase']['value']);
        $this->assertEquals('Creating Plan', $state['phase']['label']);
        $this->assertFalse($state['requires_action']);
    }

    public function test_orchestrator_event_factory_methods(): void
    {
        $convId = 'test-conv-id';

        $event = OrchestratorEvent::messageReceived($convId, 'Hello');
        $this->assertEquals('message_received', $event->type);
        $this->assertEquals($convId, $event->conversationId);
        $this->assertEquals('Hello', $event->data['message']);

        $event = OrchestratorEvent::analyzingIntent($convId);
        $this->assertEquals('analyzing_intent', $event->type);

        $event = OrchestratorEvent::planGenerated($convId, 'plan-123', 'Test Plan', 5);
        $this->assertEquals('plan_generated', $event->type);
        $this->assertEquals('plan-123', $event->data['plan_id']);
        $this->assertEquals(5, $event->data['files_affected']);

        $event = OrchestratorEvent::error($convId, 'Test error', 'planning');
        $this->assertEquals('error', $event->type);
        $this->assertEquals('Test error', $event->data['error']);
        $this->assertEquals('planning', $event->data['phase']);
    }

    public function test_orchestrator_event_broadcasts_to_correct_channel(): void
    {
        $event = OrchestratorEvent::messageReceived('test-conv', 'Hello');
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('conversation.test-conv', $channels[0]->name);
    }

    public function test_orchestrator_event_serializes_to_array(): void
    {
        $event = OrchestratorEvent::contextRetrieved('test-conv', 10, 25);
        $array = $event->toArray();

        $this->assertEquals('context_retrieved', $array['type']);
        $this->assertEquals('test-conv', $array['conversation_id']);
        $this->assertEquals(10, $array['data']['files_found']);
        $this->assertEquals(25, $array['data']['chunks_found']);
        $this->assertArrayHasKey('timestamp', $array);
    }

    public function test_conversation_scopes(): void
    {
        AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->intake()
            ->create();

        AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->approval()
            ->create();

        AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->completed()
            ->create();

        $active = AgentConversation::active()->count();
        $this->assertEquals(2, $active);

        $requiring = AgentConversation::requiringAction()->count();
        $this->assertEquals(1, $requiring);

        $inApproval = AgentConversation::inPhase(ConversationPhase::Approval)->count();
        $this->assertEquals(1, $inApproval);
    }

    public function test_message_scopes(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        AgentMessage::factory()->forConversation($conversation)->fromUser()->create();
        AgentMessage::factory()->forConversation($conversation)->fromUser()->create();
        AgentMessage::factory()->forConversation($conversation)->fromAssistant()->create();
        AgentMessage::factory()->forConversation($conversation)->fromAssistant()->clarification()->create();

        $userMessages = $conversation->messages()->fromUser()->count();
        $this->assertEquals(2, $userMessages);

        $assistantMessages = $conversation->messages()->fromAssistant()->count();
        $this->assertEquals(2, $assistantMessages);

        $requiring = $conversation->messages()->requiringAction()->count();
        $this->assertEquals(1, $requiring);
    }

    public function test_conversation_state_dto_from_conversation(): void
    {
        $intent = IntentAnalysis::factory()->create([
            'project_id' => $this->project->id,
            'intent_type' => IntentType::FeatureRequest,
        ]);

        $plan = ExecutionPlan::factory()->create([
            'project_id' => $this->project->id,
            'status' => PlanStatus::PendingReview,
        ]);

        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->approval()
            ->create([
                'current_intent_id' => $intent->id,
                'current_plan_id' => $plan->id,
            ]);

        $state = \App\DTOs\ConversationState::fromConversation($conversation);

        $this->assertTrue($state->hasIntent());
        $this->assertTrue($state->hasPlan());
        $this->assertTrue($state->isAwaitingApproval());
        $this->assertTrue($state->canApprove());
        $this->assertContains('approve_plan', $state->availableActions);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
