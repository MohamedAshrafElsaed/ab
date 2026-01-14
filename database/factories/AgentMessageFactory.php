<?php

namespace Database\Factories;

use App\Enums\AgentMessageType;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentMessage>
 */
class AgentMessageFactory extends Factory
{
    protected $model = AgentMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => AgentConversation::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'content' => fake()->paragraph(),
            'message_type' => AgentMessageType::Text,
            'attachments' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    public function forConversation(AgentConversation $conversation): static
    {
        return $this->state(fn() => [
            'conversation_id' => $conversation->id,
        ]);
    }

    public function fromUser(): static
    {
        return $this->state(fn() => [
            'role' => 'user',
        ]);
    }

    public function fromAssistant(): static
    {
        return $this->state(fn() => [
            'role' => 'assistant',
        ]);
    }

    public function fromSystem(): static
    {
        return $this->state(fn() => [
            'role' => 'system',
        ]);
    }

    public function ofType(AgentMessageType $type): static
    {
        return $this->state(fn() => [
            'message_type' => $type,
        ]);
    }

    public function text(): static
    {
        return $this->ofType(AgentMessageType::Text);
    }

    public function planPreview(string $planId = null): static
    {
        return $this->state(fn() => [
            'role' => 'assistant',
            'message_type' => AgentMessageType::PlanPreview,
            'content' => "## Implementation Plan\n\nThis is a preview of the plan.",
            'attachments' => [
                'plan_id' => $planId ?? fake()->uuid(),
            ],
        ]);
    }

    public function fileDiff(string $path = null, string $diff = null): static
    {
        return $this->state(fn() => [
            'role' => 'assistant',
            'message_type' => AgentMessageType::FileDiff,
            'content' => "Changes for {$path}",
            'attachments' => [
                'path' => $path ?? 'app/Example.php',
                'diff' => $diff ?? "--- a/app/Example.php\n+++ b/app/Example.php\n@@ -1 +1 @@\n-old\n+new",
            ],
        ]);
    }

    public function approvalRequest(): static
    {
        return $this->state(fn() => [
            'role' => 'assistant',
            'message_type' => AgentMessageType::ApprovalRequest,
            'content' => 'Would you like to proceed with these changes?',
        ]);
    }

    public function executionUpdate(int $progress = 1, int $total = 5): static
    {
        return $this->state(fn() => [
            'role' => 'assistant',
            'message_type' => AgentMessageType::ExecutionUpdate,
            'content' => "Processing file {$progress} of {$total}",
            'attachments' => [
                'progress' => $progress,
                'total' => $total,
            ],
        ]);
    }

    public function error(string $message = null): static
    {
        return $this->state(fn() => [
            'role' => 'assistant',
            'message_type' => AgentMessageType::Error,
            'content' => $message ?? 'An error occurred during processing.',
        ]);
    }

    public function clarification(array $questions = null): static
    {
        return $this->state(fn() => [
            'role' => 'assistant',
            'message_type' => AgentMessageType::Clarification,
            'content' => "I need more information:\n\n1. " . implode("\n2. ", $questions ?? ['What file should I modify?']),
            'attachments' => [
                'questions' => $questions ?? ['What file should I modify?'],
            ],
        ]);
    }

    public function withAttachments(array $attachments): static
    {
        return $this->state(fn() => [
            'attachments' => $attachments,
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state(fn() => [
            'metadata' => $metadata,
        ]);
    }

    public function at(\DateTimeInterface $timestamp): static
    {
        return $this->state(fn() => [
            'created_at' => $timestamp,
        ]);
    }
}
