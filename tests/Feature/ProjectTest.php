<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_projects(): void
    {
        $response = $this->get(route('projects.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_access_projects(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('projects.create'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_user_without_github_token_is_redirected_to_connect(): void
    {
        $response = $this->actingAs($this->user)->get(route('projects.create'));

        $response->assertRedirect(route('github.connect'));
    }

    public function test_user_with_github_token_can_access_create_page(): void
    {
        $this->createGitHubAccount($this->user);

        $response = $this->actingAs($this->user)->get(route('projects.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('projects/SelectRepository'));
    }

    public function test_user_can_view_own_project(): void
    {
        $project = Project::factory()->ready()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Show')
            ->has('project')
            ->where('project.id', $project->id)
        );
    }

    public function test_user_cannot_view_other_users_project(): void
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->ready()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->get(route('projects.show', $project));

        $response->assertForbidden();
    }

    public function test_scanning_project_redirects_to_dashboard(): void
    {
        $project = Project::factory()->scanning()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('projects.show', $project));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_can_delete_own_project(): void
    {
        $project = Project::factory()->ready()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_user_cannot_delete_other_users_project(): void
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->ready()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->delete(route('projects.destroy', $project));

        $response->assertForbidden();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_user_can_retry_failed_scan(): void
    {
        $project = Project::factory()->failed()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->post(route('projects.retry-scan', $project));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'scanning',
        ]);
    }

    public function test_user_cannot_retry_scan_on_other_users_project(): void
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->failed()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->post(route('projects.retry-scan', $project));

        $response->assertForbidden();
    }

    public function test_user_can_get_scan_status_for_own_project(): void
    {
        $project = Project::factory()->scanning()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('projects.scan-status', $project));

        $response->assertOk();
        $response->assertJsonStructure(['status', 'current_stage', 'stage_percent']);
    }

    public function test_user_cannot_get_scan_status_for_other_users_project(): void
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->scanning()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->get(route('projects.scan-status', $project));

        $response->assertForbidden();
    }

    public function test_user_can_access_ask_ai_for_ready_project(): void
    {
        $project = Project::factory()->ready()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('projects.ask', $project));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('projects/AskAI'));
    }

    public function test_user_cannot_access_ask_ai_for_other_users_project(): void
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->ready()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->get(route('projects.ask', $project));

        $response->assertForbidden();
    }

    public function test_ask_ai_redirects_when_project_not_ready(): void
    {
        $project = Project::factory()->scanning()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('projects.ask', $project));

        $response->assertRedirect(route('projects.show', $project));
    }

    protected function createGitHubAccount(User $user): SocialAccount
    {
        return SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => encrypt('test-token'),
            'token_expires_at' => now()->addYear(),
        ]);
    }
}
