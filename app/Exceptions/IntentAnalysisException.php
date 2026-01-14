<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntentAnalysisException extends Exception
{
    protected string $errorCode;

    protected array $context;

    public function __construct(
        string $message,
        string $errorCode = 'INTENT_ANALYSIS_ERROR',
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
            'INTENT_NOT_FOUND' => 404,
            'INTENT_ANALYSIS_FAILED' => 500,
            'INTENT_LOW_CONFIDENCE' => 422,
            'INTENT_VALIDATION_ERROR' => 422,
            default => 500,
        };
    }

    public static function notFound(string $intentId): self
    {
        return new self(
            "Intent analysis not found: {$intentId}",
            'INTENT_NOT_FOUND',
            ['intent_id' => $intentId]
        );
    }

    public static function analysisFailed(string $reason): self
    {
        return new self(
            "Intent analysis failed: {$reason}",
            'INTENT_ANALYSIS_FAILED',
            ['reason' => $reason]
        );
    }

    public static function lowConfidence(float $confidence, float $threshold): self
    {
        return new self(
            "Intent confidence ({$confidence}) is below threshold ({$threshold})",
            'INTENT_LOW_CONFIDENCE',
            [
                'confidence' => $confidence,
                'threshold' => $threshold,
            ]
        );
    }

    public static function emptyMessage(): self
    {
        return new self(
            'Cannot analyze empty message',
            'INTENT_EMPTY_MESSAGE'
        );
    }

    public static function invalidResponse(string $reason): self
    {
        return new self(
            "Invalid response from intent analyzer: {$reason}",
            'INTENT_INVALID_RESPONSE',
            ['reason' => $reason]
        );
    }

    public static function needsClarification(array $questions): self
    {
        return new self(
            'Intent needs clarification before proceeding',
            'INTENT_NEEDS_CLARIFICATION',
            ['clarification_questions' => $questions]
        );
    }
}
