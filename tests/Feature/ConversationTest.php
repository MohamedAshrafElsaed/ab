<?php

namespace Tests\Feature;

use App\Enums\ConversationPhase;
use App\Enums\PlanStatus;
use App\Models\AgentConversation;
use App\Models\ExecutionPlan;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->ready()->create(['user_id' => $this->user->id]);
    }

    public function test_guest_cannot_access_conversations(): void
    {
        $response = $this->getJson(route('ai.conversations.index', ['project' => $this->project->id]));

        $response->assertUnauthorized();
    }

    public function test_user_can_list_own_project_conversations(): void
    {
        AgentConversation::factory()
            ->count(3)
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('ai.conversations.index', ['project' => $this->project->id]));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_cannot_list_other_users_project_conversations(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->ready()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('ai.conversations.index', ['project' => $otherProject->id]));

        $response->assertForbidden();
    }

    public function test_user_can_create_conversation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('ai.conversations.store', ['project' => $this->project->id]), [
                'message' => 'I need help implementing a new feature',
            ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'conversation' => ['id', 'title', 'status', 'current_phase'],
        ]);
    }

    public function test_user_cannot_create_conversation_on_other_users_project(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->ready()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->postJson(route('ai.conversations.store', ['project' => $otherProject->id]), [
                'message' => 'Test message',
            ]);

        $response->assertForbidden();
    }

    public function test_user_can_view_own_conversation(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('ai.conversations.show', [
                'project' => $this->project->id,
                'conversation' => $conversation->id,
            ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'conversation' => ['id', 'title', 'status', 'current_phase'],
        ]);
    }

    public function test_user_cannot_view_other_users_conversation(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->ready()->create(['user_id' => $otherUser->id]);
        $conversation = AgentConversation::factory()
            ->forProject($otherProject)
            ->forUser($otherUser)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('ai.conversations.show', [
                'project' => $otherProject->id,
                'conversation' => $conversation->id,
            ]));

        $response->assertForbidden();
    }

    public function test_user_can_get_conversation_messages(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('ai.conversations.messages', [
                'project' => $this->project->id,
                'conversation' => $conversation->id,
            ]));

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_user_can_cancel_conversation(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->executing()
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson(route('ai.conversations.cancel', [
                'project' => $this->project->id,
                'conversation' => $conversation->id,
            ]));

        $response->assertOk();
    }

    public function test_user_can_resume_paused_conversation(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->paused()
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson(route('ai.conversations.resume', [
                'project' => $this->project->id,
                'conversation' => $conversation->id,
            ]));

        $response->assertOk();
    }

    public function test_user_can_delete_conversation(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson(route('ai.conversations.destroy', [
                'project' => $this->project->id,
                'conversation' => $conversation->id,
            ]));

        $response->assertOk();
        $this->assertDatabaseMissing('agent_conversations', ['id' => $conversation->id]);
    }

    public function test_user_cannot_delete_other_users_conversation(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->ready()->create(['user_id' => $otherUser->id]);
        $conversation = AgentConversation::factory()
            ->forProject($otherProject)
            ->forUser($otherUser)
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson(route('ai.conversations.destroy', [
                'project' => $otherProject->id,
                'conversation' => $conversation->id,
            ]));

        $response->assertForbidden();
        $this->assertDatabaseHas('agent_conversations', ['id' => $conversation->id]);
    }

    public function test_user_can_approve_plan(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->approval()
            ->create();

        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->forConversation($conversation->id)
            ->pendingReview()
            ->create();

        $conversation->update(['current_plan_id' => $plan->id]);

        $response = $this->actingAs($this->user)
            ->postJson(route('ai.conversations.approve', [
                'project' => $this->project->id,
                'conversation' => $conversation->id,
            ]), [
                'approved' => true,
            ]);

        $response->assertOk();
    }

    public function test_user_can_reject_plan(): void
    {
        $conversation = AgentConversation::factory()
            ->forProject($this->project)
            ->forUser($this->user)
            ->approval()
            ->create();

        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->forConversation($conversation->id)
            ->pendingReview()
            ->create();

        $conversation->update(['current_plan_id' => $plan->id]);

        $response = $this->actingAs($this->user)
            ->postJson(route('ai.conversations.approve', [
                'project' => $this->project->id,
                'conversation' => $conversation->id,
            ]), [
                'approved' => false,
                'feedback' => 'Please make changes to the plan',
            ]);

        $response->assertOk();
    }
}
