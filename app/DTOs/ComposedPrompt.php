<?php

namespace App\DTOs;

use JsonSerializable;

readonly class ComposedPrompt implements JsonSerializable
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt,
        public array $metadata = [],
    ) {}

    /**
     * Format for Claude API messages parameter.
     *
     * @return array{system: string, messages: array<array{role: string, content: string}>}
     */
    public function toMessages(): array
    {
        return [
            'system' => $this->systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $this->userPrompt],
            ],
        ];
    }

    /**
     * Format for Claude API with conversation history.
     *
     * @param array<array{role: string, content: string}> $history
     * @return array{system: string, messages: array<array{role: string, content: string}>}
     */
    public function toMessagesWithHistory(array $history = []): array
    {
        $messages = [];

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $this->userPrompt];

        return [
            'system' => $this->systemPrompt,
            'messages' => $messages,
        ];
    }

    /**
     * Estimate total token count using character-based approximation.
     */
    public function estimateTokens(): int
    {
        $charsPerToken = config('prompts.token_estimation.chars_per_token', 4);
        $totalChars = strlen($this->systemPrompt) + strlen($this->userPrompt);

        return (int) ceil($totalChars / $charsPerToken);
    }

    /**
     * Get breakdown of token estimates by component.
     *
     * @return array{system: int, user: int, total: int}
     */
    public function getTokenBreakdown(): array
    {
        $charsPerToken = config('prompts.token_estimation.chars_per_token', 4);

        return [
            'system' => (int) ceil(strlen($this->systemPrompt) / $charsPerToken),
            'user' => (int) ceil(strlen($this->userPrompt) / $charsPerToken),
            'total' => $this->estimateTokens(),
        ];
    }

    /**
     * Get list of templates used to compose this prompt.
     *
     * @return array<string>
     */
    public function getTemplatesUsed(): array
    {
        return $this->metadata['templates_used'] ?? [];
    }

    /**
     * Get the agent name this prompt was built for.
     */
    public function getAgentName(): ?string
    {
        return $this->metadata['agent'] ?? null;
    }

    /**
     * Check if prompt is within token limits.
     */
    public function isWithinLimits(int $maxTokens): bool
    {
        $margin = config('prompts.token_estimation.safety_margin', 0.1);
        $effectiveLimit = (int) ($maxTokens * (1 - $margin));

        return $this->estimateTokens() <= $effectiveLimit;
    }

    /**
     * Get metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Create with additional metadata.
     *
     * @param array<string, mixed> $additionalMeta
     */
    public function withMetadata(array $additionalMeta): self
    {
        return new self(
            systemPrompt: $this->systemPrompt,
            userPrompt: $this->userPrompt,
            metadata: array_merge($this->metadata, $additionalMeta),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'system_prompt' => $this->systemPrompt,
            'user_prompt' => $this->userPrompt,
            'metadata' => $this->metadata,
            'token_estimate' => $this->estimateTokens(),
        ];
    }

    /**
     * Create from array (for caching/serialization).
     *
     * @param array{system_prompt: string, user_prompt: string, metadata?: array<string, mixed>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            systemPrompt: $data['system_prompt'],
            userPrompt: $data['user_prompt'],
            metadata: $data['metadata'] ?? [],
        );
    }
}
