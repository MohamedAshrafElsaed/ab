<?php

namespace App\Exceptions;

use App\Enums\PlanStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionPlanException extends Exception
{
    protected string $errorCode;

    protected array $context;

    public function __construct(
        string $message,
        string $errorCode = 'EXECUTION_PLAN_ERROR',
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
            'PLAN_NOT_FOUND' => 404,
            'PLAN_UNAUTHORIZED' => 403,
            'PLAN_INVALID_STATUS' => 409,
            'PLAN_INVALID_TRANSITION' => 409,
            'PLAN_ALREADY_APPROVED' => 409,
            'PLAN_ALREADY_EXECUTING' => 409,
            'PLAN_EXECUTION_FAILED' => 500,
            'PLAN_VALIDATION_ERROR' => 422,
            default => 500,
        };
    }

    public static function notFound(string $planId): self
    {
        return new self(
            "Execution plan not found: {$planId}",
            'PLAN_NOT_FOUND',
            ['plan_id' => $planId]
        );
    }

    public static function unauthorized(): self
    {
        return new self(
            'You are not authorized to access this execution plan',
            'PLAN_UNAUTHORIZED'
        );
    }

    public static function invalidStatus(string $planId, PlanStatus $currentStatus, string $action): self
    {
        return new self(
            "Cannot {$action} plan in status: {$currentStatus->value}",
            'PLAN_INVALID_STATUS',
            [
                'plan_id' => $planId,
                'current_status' => $currentStatus->value,
                'attempted_action' => $action,
            ]
        );
    }

    public static function invalidTransition(PlanStatus $from, PlanStatus $to): self
    {
        return new self(
            "Cannot transition plan from {$from->value} to {$to->value}",
            'PLAN_INVALID_TRANSITION',
            [
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]
        );
    }

    public static function alreadyApproved(string $planId): self
    {
        return new self(
            'This plan has already been approved',
            'PLAN_ALREADY_APPROVED',
            ['plan_id' => $planId]
        );
    }

    public static function alreadyExecuting(string $planId): self
    {
        return new self(
            'This plan is already being executed',
            'PLAN_ALREADY_EXECUTING',
            ['plan_id' => $planId]
        );
    }

    public static function executionFailed(string $planId, string $reason, ?string $fileId = null): self
    {
        return new self(
            "Plan execution failed: {$reason}",
            'PLAN_EXECUTION_FAILED',
            [
                'plan_id' => $planId,
                'reason' => $reason,
                'file_id' => $fileId,
            ]
        );
    }

    public static function rollbackFailed(string $planId, string $reason): self
    {
        return new self(
            "Plan rollback failed: {$reason}",
            'PLAN_ROLLBACK_FAILED',
            ['plan_id' => $planId, 'reason' => $reason]
        );
    }

    public static function noFileOperations(string $planId): self
    {
        return new self(
            'Plan has no file operations to execute',
            'PLAN_NO_OPERATIONS',
            ['plan_id' => $planId]
        );
    }

    public static function fileNotFound(string $planId, string $filePath): self
    {
        return new self(
            "File not found: {$filePath}",
            'PLAN_FILE_NOT_FOUND',
            ['plan_id' => $planId, 'file_path' => $filePath]
        );
    }

    public static function generationFailed(string $reason): self
    {
        return new self(
            "Failed to generate execution plan: {$reason}",
            'PLAN_GENERATION_FAILED',
            ['reason' => $reason]
        );
    }
}
