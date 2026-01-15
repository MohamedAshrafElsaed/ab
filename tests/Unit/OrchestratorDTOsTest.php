<?php

namespace Tests\Unit;

use App\DTOs\ConversationState;
use App\Enums\AgentMessageType;
use App\Enums\ConversationPhase;
use App\Events\OrchestratorEvent;
use PHPUnit\Framework\TestCase;

class OrchestratorDTOsTest extends TestCase
{
    // =========================================================================
    // ConversationPhase Enum Tests
    // =========================================================================

    public function test_phase_labels(): void
    {
        $this->assertEquals('Receiving Request', ConversationPhase::Intake->label());
        $this->assertEquals('Creating Plan', ConversationPhase::Planning->label());
        $this->assertEquals('Making Changes', ConversationPhase::Executing->label());
        $this->assertEquals('Completed', ConversationPhase::Completed->label());
    }

    public function test_phase_descriptions(): void
    {
        $this->assertNotEmpty(ConversationPhase::Intake->description());
        $this->assertNotEmpty(ConversationPhase::Approval->description());
    }

    public function test_phase_icons(): void
    {
        $this->assertEquals('message-circle', ConversationPhase::Intake->icon());
        $this->assertEquals('check-circle', ConversationPhase::Approval->icon());
        $this->assertEquals('check', ConversationPhase::Completed->icon());
    }

    public function test_phase_colors(): void
    {
        $this->assertEquals('blue', ConversationPhase::Intake->color());
        $this->assertEquals('green', ConversationPhase::Completed->color());
        $this->assertEquals('red', ConversationPhase::Failed->color());
    }

    public function test_phase_is_terminal(): void
    {
        $this->assertTrue(ConversationPhase::Completed->isTerminal());
        $this->assertTrue(ConversationPhase::Failed->isTerminal());
        $this->assertFalse(ConversationPhase::Intake->isTerminal());
        $this->assertFalse(ConversationPhase::Executing->isTerminal());
    }

    public function test_phase_is_active(): void
    {
        $this->assertTrue(ConversationPhase::Discovery->isActive());
        $this->assertTrue(ConversationPhase::Planning->isActive());
        $this->assertTrue(ConversationPhase::Executing->isActive());
        $this->assertFalse(ConversationPhase::Intake->isActive());
        $this->assertFalse(ConversationPhase::Approval->isActive());
    }

    public function test_phase_requires_user_action(): void
    {
        $this->assertTrue(ConversationPhase::Clarification->requiresUserAction());
        $this->assertTrue(ConversationPhase::Approval->requiresUserAction());
        $this->assertFalse(ConversationPhase::Planning->requiresUserAction());
        $this->assertFalse(ConversationPhase::Executing->requiresUserAction());
    }

    public function test_phase_valid_transitions(): void
    {
        // Intake can go to clarification or discovery
        $this->assertTrue(ConversationPhase::Intake->canTransitionTo(ConversationPhase::Clarification));
        $this->assertTrue(ConversationPhase::Intake->canTransitionTo(ConversationPhase::Discovery));
        $this->assertFalse(ConversationPhase::Intake->canTransitionTo(ConversationPhase::Executing));

        // Discovery to Planning
        $this->assertTrue(ConversationPhase::Discovery->canTransitionTo(ConversationPhase::Planning));
        $this->assertFalse(ConversationPhase::Discovery->canTransitionTo(ConversationPhase::Completed));

        // Approval to Executing or back to Planning
        $this->assertTrue(ConversationPhase::Approval->canTransitionTo(ConversationPhase::Executing));
        $this->assertTrue(ConversationPhase::Approval->canTransitionTo(ConversationPhase::Planning));

        // Completed cannot transition
        $this->assertFalse(ConversationPhase::Completed->canTransitionTo(ConversationPhase::Intake));

        // Failed can restart
        $this->assertTrue(ConversationPhase::Failed->canTransitionTo(ConversationPhase::Intake));
    }

    public function test_phase_next_phases(): void
    {
        $next = ConversationPhase::Intake->nextPhases();
        $this->assertContains('clarification', $next);
        $this->assertContains('discovery', $next);

        $next = ConversationPhase::Completed->nextPhases();
        $this->assertEmpty($next);
    }

    public function test_phase_values(): void
    {
        $values = ConversationPhase::values();
        $this->assertContains('intake', $values);
        $this->assertContains('completed', $values);
        $this->assertCount(8, $values);
    }

    // =========================================================================
    // AgentMessageType Enum Tests
    // =========================================================================

    public function test_message_type_labels(): void
    {
        $this->assertEquals('Message', AgentMessageType::Text->label());
        $this->assertEquals('Execution Plan', AgentMessageType::PlanPreview->label());
        $this->assertEquals('File Changes', AgentMessageType::FileDiff->label());
        $this->assertEquals('Error', AgentMessageType::Error->label());
    }

    public function test_message_type_icons(): void
    {
        $this->assertEquals('message-square', AgentMessageType::Text->icon());
        $this->assertEquals('clipboard-list', AgentMessageType::PlanPreview->icon());
        $this->assertEquals('alert-circle', AgentMessageType::Error->icon());
    }

    public function test_message_type_colors(): void
    {
        $this->assertEquals('gray', AgentMessageType::Text->color());
        $this->assertEquals('purple', AgentMessageType::PlanPreview->color());
        $this->assertEquals('red', AgentMessageType::Error->color());
    }

    public function test_message_type_requires_action(): void
    {
        $this->assertTrue(AgentMessageType::ApprovalRequest->requiresAction());
        $this->assertTrue(AgentMessageType::Clarification->requiresAction());
        $this->assertFalse(AgentMessageType::Text->requiresAction());
        $this->assertFalse(AgentMessageType::PlanPreview->requiresAction());
    }

    public function test_message_type_has_attachments(): void
    {
        $this->assertTrue(AgentMessageType::PlanPreview->hasAttachments());
        $this->assertTrue(AgentMessageType::FileDiff->hasAttachments());
        $this->assertTrue(AgentMessageType::CodeContext->hasAttachments());
        $this->assertFalse(AgentMessageType::Text->hasAttachments());
        $this->assertFalse(AgentMessageType::Error->hasAttachments());
    }

    public function test_message_type_values(): void
    {
        $values = AgentMessageType::values();
        $this->assertContains('text', $values);
        $this->assertContains('plan_preview', $values);
        $this->assertCount(9, $values);
    }

    // =========================================================================
    // OrchestratorEvent Tests
    // =========================================================================

    public function test_event_message_received(): void
    {
        $event = OrchestratorEvent::messageReceived('conv-123', 'Hello world');

        $this->assertEquals('message_received', $event->type);
        $this->assertEquals('conv-123', $event->conversationId);
        $this->assertEquals('Hello world', $event->data['message']);
        $this->assertNotEmpty($event->timestamp);
    }

    public function test_event_phase_changed(): void
    {
        $event = OrchestratorEvent::phaseChanged('conv-123', 'intake', 'discovery');

        $this->assertEquals('phase_changed', $event->type);
        $this->assertEquals('intake', $event->data['from_phase']);
        $this->assertEquals('discovery', $event->data['to_phase']);
    }

    public function test_event_analyzing_intent(): void
    {
        $event = OrchestratorEvent::analyzingIntent('conv-123');

        $this->assertEquals('analyzing_intent', $event->type);
        $this->assertArrayHasKey('status', $event->data);
    }

    public function test_event_intent_analyzed(): void
    {
        $event = OrchestratorEvent::intentAnalyzed('conv-123', 'feature_request', 0.95, 'Feature Request');

        $this->assertEquals('intent_analyzed', $event->type);
        $this->assertEquals('feature_request', $event->data['intent_type']);
        $this->assertEquals(0.95, $event->data['confidence']);
        $this->assertEquals('Feature Request', $event->data['summary']);
    }

    public function test_event_clarification_needed(): void
    {
        $questions = ['What file?', 'What feature?'];
        $event = OrchestratorEvent::clarificationNeeded('conv-123', $questions);

        $this->assertEquals('clarification_needed', $event->type);
        $this->assertCount(2, $event->data['questions']);
    }

    public function test_event_context_retrieved(): void
    {
        $event = OrchestratorEvent::contextRetrieved('conv-123', 10, 50);

        $this->assertEquals('context_retrieved', $event->type);
        $this->assertEquals(10, $event->data['files_found']);
        $this->assertEquals(50, $event->data['chunks_found']);
    }

    public function test_event_plan_generated(): void
    {
        $event = OrchestratorEvent::planGenerated('conv-123', 'plan-456', 'Add Login', 5);

        $this->assertEquals('plan_generated', $event->type);
        $this->assertEquals('plan-456', $event->data['plan_id']);
        $this->assertEquals('Add Login', $event->data['title']);
        $this->assertEquals(5, $event->data['files_affected']);
    }

    public function test_event_execution_progress(): void
    {
        $event = OrchestratorEvent::executionProgress('conv-123', 3, 10, 'app/Test.php');

        $this->assertEquals('execution_progress', $event->type);
        $this->assertEquals(3, $event->data['completed']);
        $this->assertEquals(10, $event->data['total']);
        $this->assertEquals(30, $event->data['percentage']);
        $this->assertEquals('app/Test.php', $event->data['current_file']);
    }

    public function test_event_execution_completed(): void
    {
        $event = OrchestratorEvent::executionCompleted('conv-123', 8, 2);

        $this->assertEquals('execution_completed', $event->type);
        $this->assertEquals(8, $event->data['files_completed']);
        $this->assertEquals(2, $event->data['files_failed']);
        $this->assertFalse($event->data['success']);
    }

    public function test_event_error(): void
    {
        $event = OrchestratorEvent::error('conv-123', 'Something went wrong', 'planning');

        $this->assertEquals('error', $event->type);
        $this->assertEquals('Something went wrong', $event->data['error']);
        $this->assertEquals('planning', $event->data['phase']);
    }

    public function test_event_to_array(): void
    {
        $event = OrchestratorEvent::messageReceived('conv-123', 'Hello');
        $array = $event->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('conversation_id', $array);
        $this->assertArrayHasKey('timestamp', $array);
    }

    public function test_event_json_serializable(): void
    {
        $event = OrchestratorEvent::planGenerated('conv-123', 'plan-1', 'Test', 3);
        $json = json_encode($event);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('plan_generated', $decoded['type']);
    }

    public function test_event_broadcast_channel(): void
    {
        $event = OrchestratorEvent::messageReceived('conv-123', 'Hello');
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('conversation.conv-123', $channels[0]->name);
    }

    public function test_event_broadcast_as(): void
    {
        $event = OrchestratorEvent::messageReceived('conv-123', 'Hello');

        $this->assertEquals('orchestrator.event', $event->broadcastAs());
    }

    // =========================================================================
    // ConversationState DTO Tests
    // =========================================================================

    public function test_conversation_state_construction(): void
    {
        $state = new ConversationState(
            conversationId: 'conv-123',
            phase: ConversationPhase::Approval,
            status: 'active',
            title: 'Test Conversation',
            currentIntent: null,
            currentPlan: null,
            pendingExecutions: null,
            availableActions: ['approve_plan', 'reject_plan'],
            metadata: ['started_at' => '2024-01-01T00:00:00Z'],
        );

        $this->assertEquals('conv-123', $state->conversationId);
        $this->assertEquals(ConversationPhase::Approval, $state->phase);
        $this->assertEquals('active', $state->status);
        $this->assertEquals('Test Conversation', $state->title);
    }

    public function test_conversation_state_helper_methods(): void
    {
        $state = new ConversationState(
            conversationId: 'conv-123',
            phase: ConversationPhase::Approval,
            status: 'active',
            title: null,
            currentIntent: null,
            currentPlan: null,
            pendingExecutions: null,
            availableActions: ['approve_plan', 'send_message'],
            metadata: [],
        );

        $this->assertTrue($state->isAwaitingApproval());
        $this->assertFalse($state->isExecuting());
        $this->assertFalse($state->isTerminal());
        $this->assertFalse($state->hasPlan());
        $this->assertFalse($state->hasIntent());
        $this->assertTrue($state->canSendMessage());
        $this->assertTrue($state->canApprove());
    }

    public function test_conversation_state_to_array(): void
    {
        $state = new ConversationState(
            conversationId: 'conv-123',
            phase: ConversationPhase::Planning,
            status: 'active',
            title: 'Test',
            currentIntent: null,
            currentPlan: null,
            pendingExecutions: null,
            availableActions: ['cancel'],
            metadata: ['test' => 'value'],
        );

        $array = $state->toArray();

        $this->assertArrayHasKey('conversation_id', $array);
        $this->assertArrayHasKey('phase', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('available_actions', $array);
        $this->assertArrayHasKey('metadata', $array);

        $this->assertEquals('conv-123', $array['conversation_id']);
        $this->assertEquals('planning', $array['phase']['value']);
        $this->assertEquals('Creating Plan', $array['phase']['label']);
        $this->assertFalse($array['phase']['is_terminal']);
    }

    public function test_conversation_state_json_serializable(): void
    {
        $state = new ConversationState(
            conversationId: 'conv-123',
            phase: ConversationPhase::Intake,
            status: 'active',
            title: null,
            currentIntent: null,
            currentPlan: null,
            pendingExecutions: null,
            availableActions: [],
            metadata: [],
        );

        $json = json_encode($state);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals('conv-123', $decoded['conversation_id']);
    }
}
