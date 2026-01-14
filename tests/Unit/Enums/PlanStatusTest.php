<?php

namespace Tests\Unit\Enums;

use App\Enums\PlanStatus;
use PHPUnit\Framework\TestCase;

class PlanStatusTest extends TestCase
{
    public function test_draft_is_modifiable(): void
    {
        $this->assertTrue(PlanStatus::Draft->isModifiable());
    }

    public function test_pending_review_is_modifiable(): void
    {
        $this->assertTrue(PlanStatus::PendingReview->isModifiable());
    }

    public function test_approved_is_not_modifiable(): void
    {
        $this->assertFalse(PlanStatus::Approved->isModifiable());
    }

    public function test_executing_is_not_modifiable(): void
    {
        $this->assertFalse(PlanStatus::Executing->isModifiable());
    }

    public function test_completed_is_not_modifiable(): void
    {
        $this->assertFalse(PlanStatus::Completed->isModifiable());
    }

    public function test_failed_is_not_modifiable(): void
    {
        $this->assertFalse(PlanStatus::Failed->isModifiable());
    }

    public function test_approved_can_execute(): void
    {
        $this->assertTrue(PlanStatus::Approved->canExecute());
    }

    public function test_draft_cannot_execute(): void
    {
        $this->assertFalse(PlanStatus::Draft->canExecute());
    }

    public function test_pending_review_cannot_execute(): void
    {
        $this->assertFalse(PlanStatus::PendingReview->canExecute());
    }

    public function test_executing_cannot_execute(): void
    {
        $this->assertFalse(PlanStatus::Executing->canExecute());
    }

    public function test_can_transition_from_draft_to_pending_review(): void
    {
        $this->assertTrue(PlanStatus::Draft->canTransitionTo(PlanStatus::PendingReview));
    }

    public function test_can_transition_from_pending_review_to_approved(): void
    {
        $this->assertTrue(PlanStatus::PendingReview->canTransitionTo(PlanStatus::Approved));
    }

    public function test_can_transition_from_pending_review_to_rejected(): void
    {
        $this->assertTrue(PlanStatus::PendingReview->canTransitionTo(PlanStatus::Rejected));
    }

    public function test_can_transition_from_approved_to_executing(): void
    {
        $this->assertTrue(PlanStatus::Approved->canTransitionTo(PlanStatus::Executing));
    }

    public function test_can_transition_from_executing_to_completed(): void
    {
        $this->assertTrue(PlanStatus::Executing->canTransitionTo(PlanStatus::Completed));
    }

    public function test_can_transition_from_executing_to_failed(): void
    {
        $this->assertTrue(PlanStatus::Executing->canTransitionTo(PlanStatus::Failed));
    }

    public function test_cannot_transition_from_completed(): void
    {
        $this->assertFalse(PlanStatus::Completed->canTransitionTo(PlanStatus::Draft));
        $this->assertFalse(PlanStatus::Completed->canTransitionTo(PlanStatus::Executing));
    }

    public function test_badge_colors_are_strings(): void
    {
        $this->assertIsString(PlanStatus::Draft->badgeColor());
        $this->assertIsString(PlanStatus::Approved->badgeColor());
        $this->assertIsString(PlanStatus::Failed->badgeColor());
    }
}
