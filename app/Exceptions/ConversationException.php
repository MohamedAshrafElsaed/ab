<?php

namespace App\Exceptions;

use App\Enums\ConversationPhase;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationException extends Exception
{
    protected string $errorCode;

    protected array $context;

    public function __construct(
        string $message,
        string $errorCode = 'CONVERSATION_ERROR',
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
            'CONVERSATION_NOT_FOUND' => 404,
            'CONVERSATION_UNAUTHORIZED' => 403,
            'CONVERSATION_INVALID_STATE' => 409,
            'CONVERSATION_INVALID_TRANSITION' => 409,
            'CONVERSATION_ALREADY_COMPLETED' => 409,
            'CONVERSATION_VALIDATION_ERROR' => 422,
            default => 500,
        };
    }

    public static function notFound(string $conversationId): self
    {
        return new self(
            "Conversation not found: {$conversationId}",
            'CONVERSATION_NOT_FOUND',
            ['conversation_id' => $conversationId]
        );
    }

    public static function unauthorized(): self
    {
        return new self(
            'You are not authorized to access this conversation',
            'CONVERSATION_UNAUTHORIZED'
        );
    }

    public static function invalidState(string $conversationId, string $currentState, string $expectedState): self
    {
        return new self(
            "Conversation is in invalid state. Expected: {$expectedState}, Current: {$currentState}",
            'CONVERSATION_INVALID_STATE',
            [
                'conversation_id' => $conversationId,
                'current_state' => $currentState,
                'expected_state' => $expectedState,
            ]
        );
    }

    public static function invalidTransition(ConversationPhase $from, ConversationPhase $to): self
    {
        return new self(
            "Cannot transition from {$from->value} to {$to->value}",
            'CONVERSATION_INVALID_TRANSITION',
            [
                'from_phase' => $from->value,
                'to_phase' => $to->value,
            ]
        );
    }

    public static function alreadyCompleted(string $conversationId): self
    {
        return new self(
            'This conversation has already been completed',
            'CONVERSATION_ALREADY_COMPLETED',
            ['conversation_id' => $conversationId]
        );
    }

    public static function noPlanAvailable(string $conversationId): self
    {
        return new self(
            'No plan is available for this conversation',
            'CONVERSATION_NO_PLAN',
            ['conversation_id' => $conversationId]
        );
    }

    public static function cannotCancel(string $conversationId, string $reason): self
    {
        return new self(
            "Cannot cancel conversation: {$reason}",
            'CONVERSATION_CANNOT_CANCEL',
            ['conversation_id' => $conversationId, 'reason' => $reason]
        );
    }

    public static function cannotResume(string $conversationId, string $reason): self
    {
        return new self(
            "Cannot resume conversation: {$reason}",
            'CONVERSATION_CANNOT_RESUME',
            ['conversation_id' => $conversationId, 'reason' => $reason]
        );
    }

    public static function processingError(string $conversationId, string $reason): self
    {
        return new self(
            "Error processing conversation: {$reason}",
            'CONVERSATION_PROCESSING_ERROR',
            ['conversation_id' => $conversationId, 'reason' => $reason]
        );
    }
}
