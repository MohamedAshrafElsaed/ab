<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $project->user);
        $this->assertEquals($user->id, $project->user->id);
    }

    public function test_project_status_checks(): void
    {
        $pending = Project::factory()->create(['status' => 'pending']);
        $scanning = Project::factory()->scanning()->create();
        $ready = Project::factory()->ready()->create();
        $failed = Project::factory()->failed()->create();

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isScanning());
        $this->assertFalse($pending->isReady());
        $this->assertFalse($pending->isFailed());

        $this->assertFalse($scanning->isPending());
        $this->assertTrue($scanning->isScanning());
        $this->assertFalse($scanning->isReady());
        $this->assertFalse($scanning->isFailed());

        $this->assertFalse($ready->isPending());
        $this->assertFalse($ready->isScanning());
        $this->assertTrue($ready->isReady());
        $this->assertFalse($ready->isFailed());

        $this->assertFalse($failed->isPending());
        $this->assertFalse($failed->isScanning());
        $this->assertFalse($failed->isReady());
        $this->assertTrue($failed->isFailed());
    }

    public function test_project_mark_scanning(): void
    {
        $project = Project::factory()->create(['status' => 'pending']);

        $project->markScanning();

        $this->assertEquals('scanning', $project->fresh()->status);
        $this->assertNull($project->fresh()->last_error);
    }

    public function test_project_mark_ready(): void
    {
        $project = Project::factory()->scanning()->create();
        $commitSha = 'abc123';

        $project->markReady($commitSha);

        $project->refresh();
        $this->assertEquals('ready', $project->status);
        $this->assertEquals($commitSha, $project->last_commit_sha);
        $this->assertNotNull($project->scanned_at);
        $this->assertNull($project->last_error);
    }

    public function test_project_mark_failed(): void
    {
        $project = Project::factory()->scanning()->create();
        $error = 'Clone failed: authentication required';

        $project->markFailed($error);

        $project->refresh();
        $this->assertEquals('failed', $project->status);
        $this->assertEquals($error, $project->last_error);
    }

    public function test_project_update_progress(): void
    {
        $project = Project::factory()->scanning()->create();

        $project->updateProgress('indexing', 75);

        $project->refresh();
        $this->assertEquals('indexing', $project->current_stage);
        $this->assertEquals(75, $project->stage_percent);
    }

    public function test_project_update_stats(): void
    {
        $project = Project::factory()->create();

        $project->updateStats(100, 5000, 250000);

        $project->refresh();
        $this->assertEquals(100, $project->total_files);
        $this->assertEquals(5000, $project->total_lines);
        $this->assertEquals(250000, $project->total_size_bytes);
    }

    public function test_project_owner_accessor(): void
    {
        $project = Project::factory()->create(['repo_full_name' => 'owner/repo-name']);

        $this->assertEquals('owner', $project->owner);
    }

    public function test_project_repo_name_accessor(): void
    {
        $project = Project::factory()->create(['repo_full_name' => 'owner/repo-name']);

        $this->assertEquals('repo-name', $project->repo_name);
    }

    public function test_project_active_branch_returns_selected_branch(): void
    {
        $project = Project::factory()->create([
            'default_branch' => 'main',
            'selected_branch' => 'develop',
        ]);

        $this->assertEquals('develop', $project->active_branch);
    }

    public function test_project_active_branch_falls_back_to_default(): void
    {
        $project = Project::factory()->create([
            'default_branch' => 'main',
            'selected_branch' => null,
        ]);

        $this->assertEquals('main', $project->active_branch);
    }

    public function test_project_github_url(): void
    {
        $project = Project::factory()->create(['repo_full_name' => 'owner/repo']);

        $this->assertEquals('https://github.com/owner/repo', $project->getGitHubUrl());
    }

    public function test_project_github_file_url(): void
    {
        $project = Project::factory()->create([
            'repo_full_name' => 'owner/repo',
            'default_branch' => 'main',
        ]);

        $this->assertEquals(
            'https://github.com/owner/repo/blob/main/src/file.php',
            $project->getGitHubFileUrl('src/file.php')
        );

        $this->assertEquals(
            'https://github.com/owner/repo/blob/main/src/file.php#L42',
            $project->getGitHubFileUrl('src/file.php', 42)
        );
    }

    public function test_project_git_clone_url(): void
    {
        $project = Project::factory()->create(['repo_full_name' => 'owner/repo']);

        $this->assertEquals('https://github.com/owner/repo.git', $project->getGitCloneUrl());
    }

    public function test_project_needs_migration(): void
    {
        $projectNull = Project::factory()->create(['scan_output_version' => null]);
        $projectOld = Project::factory()->create(['scan_output_version' => '1.0.0']);
        $projectCurrent = Project::factory()->create(['scan_output_version' => '2.1.0']);
        $projectNewer = Project::factory()->create(['scan_output_version' => '3.0.0']);

        $this->assertTrue($projectNull->needsMigration());
        $this->assertTrue($projectOld->needsMigration());
        $this->assertFalse($projectCurrent->needsMigration());
        $this->assertFalse($projectNewer->needsMigration());
    }

    public function test_project_casts_stack_info_to_array(): void
    {
        $stackInfo = [
            'framework' => 'laravel',
            'frontend' => ['vue', 'inertia'],
        ];

        $project = Project::factory()->create(['stack_info' => $stackInfo]);

        $this->assertIsArray($project->stack_info);
        $this->assertEquals('laravel', $project->stack_info['framework']);
    }

    public function test_project_casts_scanned_at_to_datetime(): void
    {
        $project = Project::factory()->ready()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $project->scanned_at);
    }
}
