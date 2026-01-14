<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\User;
use App\Services\StorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StorageQuotaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StorageQuotaService();
    }

    public function test_get_usage_for_user_with_no_projects(): void
    {
        $user = User::factory()->create();

        $usage = $this->service->getUsageForUser($user);

        $this->assertEquals(0, $usage['used']);
        $this->assertEquals(0, $usage['percentage']);
        $this->assertEquals(0, $usage['projects_count']);
    }

    public function test_get_usage_for_user_with_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(3)->create([
            'user_id' => $user->id,
            'total_size_bytes' => 1000000, // 1MB each
        ]);

        $usage = $this->service->getUsageForUser($user);

        $this->assertEquals(3000000, $usage['used']);
        $this->assertEquals(3, $usage['projects_count']);
        $this->assertGreaterThan(0, $usage['percentage']);
    }

    public function test_can_create_project_returns_true_for_new_user(): void
    {
        $user = User::factory()->create();

        $result = $this->service->canCreateProject($user);

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    public function test_can_create_project_returns_false_at_max_projects(): void
    {
        config(['projects.max_projects_per_user' => 5]);
        $this->service = new StorageQuotaService();

        $user = User::factory()->create();
        Project::factory()->count(5)->create(['user_id' => $user->id]);

        $result = $this->service->canCreateProject($user);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('Maximum number', $result['reason']);
    }

    public function test_has_storage_for_returns_true_when_under_quota(): void
    {
        $user = User::factory()->create();

        $result = $this->service->hasStorageFor($user, 1000000); // 1MB

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    public function test_is_file_size_allowed_returns_true_for_small_files(): void
    {
        $result = $this->service->isFileSizeAllowed(1024 * 1024); // 1MB

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    public function test_is_file_size_allowed_returns_false_for_large_files(): void
    {
        config(['projects.max_file_size' => 1024 * 1024]); // 1MB max
        $this->service = new StorageQuotaService();

        $result = $this->service->isFileSizeAllowed(200 * 1024 * 1024); // 200MB

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('exceeds maximum', $result['reason']);
    }

    public function test_get_project_stats_returns_correct_data(): void
    {
        $project = Project::factory()->ready()->create([
            'total_files' => 100,
            'total_size_bytes' => 5000000,
        ]);

        $stats = $this->service->getProjectStats($project);

        $this->assertEquals(100, $stats['total_files']);
        $this->assertEquals(5000000, $stats['total_size']);
        $this->assertArrayHasKey('size_formatted', $stats);
    }
}
