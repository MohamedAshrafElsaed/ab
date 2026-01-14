<?php

namespace Tests\Unit\Enums;

use App\Enums\ConversationPhase;
use PHPUnit\Framework\TestCase;

class ConversationPhaseTest extends TestCase
{
    public function test_can_transition_from_intake_to_clarification(): void
    {
        $this->assertTrue(ConversationPhase::Intake->canTransitionTo(ConversationPhase::Clarification));
    }

    public function test_can_transition_from_intake_to_discovery(): void
    {
        $this->assertTrue(ConversationPhase::Intake->canTransitionTo(ConversationPhase::Discovery));
    }

    public function test_can_transition_from_intake_to_planning(): void
    {
        $this->assertTrue(ConversationPhase::Intake->canTransitionTo(ConversationPhase::Planning));
    }

    public function test_cannot_transition_from_completed_to_any(): void
    {
        $this->assertFalse(ConversationPhase::Completed->canTransitionTo(ConversationPhase::Intake));
        $this->assertFalse(ConversationPhase::Completed->canTransitionTo(ConversationPhase::Planning));
        $this->assertFalse(ConversationPhase::Completed->canTransitionTo(ConversationPhase::Executing));
    }

    public function test_cannot_transition_from_failed_to_any(): void
    {
        $this->assertFalse(ConversationPhase::Failed->canTransitionTo(ConversationPhase::Intake));
        $this->assertFalse(ConversationPhase::Failed->canTransitionTo(ConversationPhase::Planning));
        $this->assertFalse(ConversationPhase::Failed->canTransitionTo(ConversationPhase::Completed));
    }

    public function test_terminal_phases(): void
    {
        $this->assertTrue(ConversationPhase::Completed->isTerminal());
        $this->assertTrue(ConversationPhase::Failed->isTerminal());

        $this->assertFalse(ConversationPhase::Intake->isTerminal());
        $this->assertFalse(ConversationPhase::Planning->isTerminal());
        $this->assertFalse(ConversationPhase::Executing->isTerminal());
    }

    public function test_phases_requiring_user_action(): void
    {
        $this->assertTrue(ConversationPhase::Clarification->requiresUserAction());
        $this->assertTrue(ConversationPhase::Approval->requiresUserAction());

        $this->assertFalse(ConversationPhase::Intake->requiresUserAction());
        $this->assertFalse(ConversationPhase::Planning->requiresUserAction());
        $this->assertFalse(ConversationPhase::Executing->requiresUserAction());
    }

    public function test_phase_labels(): void
    {
        $this->assertIsString(ConversationPhase::Intake->label());
        $this->assertIsString(ConversationPhase::Planning->label());
        $this->assertNotEmpty(ConversationPhase::Intake->label());
    }

    public function test_phase_descriptions(): void
    {
        $this->assertIsString(ConversationPhase::Intake->description());
        $this->assertIsString(ConversationPhase::Planning->description());
        $this->assertNotEmpty(ConversationPhase::Intake->description());
    }

    public function test_phase_colors(): void
    {
        $this->assertIsString(ConversationPhase::Intake->color());
        $this->assertIsString(ConversationPhase::Completed->color());
        $this->assertIsString(ConversationPhase::Failed->color());
    }

    public function test_can_transition_from_approval_to_executing(): void
    {
        $this->assertTrue(ConversationPhase::Approval->canTransitionTo(ConversationPhase::Executing));
    }

    public function test_can_transition_from_executing_to_completed(): void
    {
        $this->assertTrue(ConversationPhase::Executing->canTransitionTo(ConversationPhase::Completed));
    }

    public function test_can_transition_from_executing_to_failed(): void
    {
        $this->assertTrue(ConversationPhase::Executing->canTransitionTo(ConversationPhase::Failed));
    }
}
