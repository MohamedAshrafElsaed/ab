<?php

namespace Tests\Unit\Policies;

use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ProjectPolicy();
    }

    public function test_view_any_returns_true(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_returns_true_for_owner(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $project));
    }

    public function test_view_returns_false_for_non_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->view($user, $project));
    }

    public function test_create_returns_true_with_github_token(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => encrypt('token'),
            'token_expires_at' => now()->addYear(),
        ]);

        $this->assertTrue($this->policy->create($user));
    }

    public function test_create_returns_false_without_github_token(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy->create($user));
    }

    public function test_update_returns_true_for_owner(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $project));
    }

    public function test_update_returns_false_for_non_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->update($user, $project));
    }

    public function test_delete_returns_true_for_owner(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $project));
    }

    public function test_delete_returns_false_for_non_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->delete($user, $project));
    }

    public function test_retry_scan_returns_true_for_owner_with_failed_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->failed()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->retryScan($user, $project));
    }

    public function test_retry_scan_returns_false_for_non_failed_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ready()->create(['user_id' => $user->id]);

        $this->assertFalse($this->policy->retryScan($user, $project));
    }

    public function test_ask_ai_returns_true_for_owner_with_ready_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->ready()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->askAI($user, $project));
    }

    public function test_ask_ai_returns_false_for_non_ready_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->scanning()->create(['user_id' => $user->id]);

        $this->assertFalse($this->policy->askAI($user, $project));
    }
}
