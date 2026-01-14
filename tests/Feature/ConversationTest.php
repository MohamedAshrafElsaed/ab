<?php

namespace Tests\Feature;

use App\Models\AgentConversation;
use App\Models\Project;
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

    public function test_user_cannot_list_other_users_project_conversations(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->ready()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('ai.conversations.index', ['project' => $otherProject->id]));

        $response->assertForbidden();
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
}
