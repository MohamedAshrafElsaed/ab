<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectException extends Exception
{
    protected string $errorCode;

    protected array $context;

    public function __construct(
        string $message,
        string $errorCode = 'PROJECT_ERROR',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        $statusCode = $this->getHttpStatusCode();

        return response()->json([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'context' => $this->context,
            ],
        ], $statusCode);
    }

    protected function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            'PROJECT_NOT_FOUND' => 404,
            'PROJECT_UNAUTHORIZED' => 403,
            'PROJECT_SCAN_IN_PROGRESS' => 409,
            'PROJECT_ALREADY_EXISTS' => 409,
            'PROJECT_VALIDATION_ERROR' => 422,
            default => 500,
        };
    }

    public static function notFound(string $projectId): self
    {
        return new self(
            "Project not found: {$projectId}",
            'PROJECT_NOT_FOUND',
            ['project_id' => $projectId]
        );
    }

    public static function unauthorized(): self
    {
        return new self(
            'You are not authorized to access this project',
            'PROJECT_UNAUTHORIZED'
        );
    }

    public static function scanInProgress(string $projectId): self
    {
        return new self(
            'A scan is already in progress for this project',
            'PROJECT_SCAN_IN_PROGRESS',
            ['project_id' => $projectId]
        );
    }

    public static function alreadyExists(string $repoFullName): self
    {
        return new self(
            "Project for repository {$repoFullName} already exists",
            'PROJECT_ALREADY_EXISTS',
            ['repo_full_name' => $repoFullName]
        );
    }

    public static function scanFailed(string $projectId, string $reason): self
    {
        return new self(
            "Project scan failed: {$reason}",
            'PROJECT_SCAN_FAILED',
            ['project_id' => $projectId, 'reason' => $reason]
        );
    }

    public static function cloneFailed(string $repoUrl, string $reason): self
    {
        return new self(
            "Failed to clone repository: {$reason}",
            'PROJECT_CLONE_FAILED',
            ['repo_url' => $repoUrl, 'reason' => $reason]
        );
    }

    public static function notReady(string $projectId): self
    {
        return new self(
            'Project is not ready. Please wait for the scan to complete.',
            'PROJECT_NOT_READY',
            ['project_id' => $projectId]
        );
    }
}
