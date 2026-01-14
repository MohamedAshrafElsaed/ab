<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ProjectException;
use PHPUnit\Framework\TestCase;

class ProjectExceptionTest extends TestCase
{
    public function test_not_found_exception(): void
    {
        $exception = ProjectException::notFound('project-123');

        $this->assertEquals('PROJECT_NOT_FOUND', $exception->getErrorCode());
        $this->assertStringContainsString('project-123', $exception->getMessage());
        $this->assertArrayHasKey('project_id', $exception->getContext());
    }

    public function test_unauthorized_exception(): void
    {
        $exception = ProjectException::unauthorized();

        $this->assertEquals('PROJECT_UNAUTHORIZED', $exception->getErrorCode());
        $this->assertStringContainsString('not authorized', $exception->getMessage());
    }

    public function test_scan_in_progress_exception(): void
    {
        $exception = ProjectException::scanInProgress('project-123');

        $this->assertEquals('PROJECT_SCAN_IN_PROGRESS', $exception->getErrorCode());
        $this->assertStringContainsString('already in progress', $exception->getMessage());
    }

    public function test_already_exists_exception(): void
    {
        $exception = ProjectException::alreadyExists('owner/repo');

        $this->assertEquals('PROJECT_ALREADY_EXISTS', $exception->getErrorCode());
        $this->assertStringContainsString('owner/repo', $exception->getMessage());
    }

    public function test_scan_failed_exception(): void
    {
        $exception = ProjectException::scanFailed('project-123', 'Authentication error');

        $this->assertEquals('PROJECT_SCAN_FAILED', $exception->getErrorCode());
        $this->assertArrayHasKey('reason', $exception->getContext());
    }

    public function test_clone_failed_exception(): void
    {
        $exception = ProjectException::cloneFailed('https://github.com/repo.git', 'Network error');

        $this->assertEquals('PROJECT_CLONE_FAILED', $exception->getErrorCode());
        $this->assertArrayHasKey('repo_url', $exception->getContext());
    }

    public function test_not_ready_exception(): void
    {
        $exception = ProjectException::notReady('project-123');

        $this->assertEquals('PROJECT_NOT_READY', $exception->getErrorCode());
        $this->assertStringContainsString('not ready', $exception->getMessage());
    }
}
