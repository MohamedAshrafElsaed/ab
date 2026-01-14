<?php

namespace App\Services\AskAI\DTO;

readonly class AuditLogEntry
{
    public function __construct(
        public string $path,
        public string $chunkId,
        public int $startLine,
        public int $endLine,
        public string $sha1,
        public array $quotedSnippets = [],
        public float $relevanceScore = 0.0,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'chunk_id' => $this->chunkId,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
            'sha1' => $this->sha1,
            'quoted_snippets' => $this->quotedSnippets,
            'relevance_score' => round($this->relevanceScore, 3),
        ];
    }

    public static function fromRetrievedChunk(RetrievedChunk $chunk, array $quotedSnippets = []): self
    {
        return new self(
            path: $chunk->path,
            chunkId: $chunk->chunkId,
            startLine: $chunk->startLine,
            endLine: $chunk->endLine,
            sha1: $chunk->sha1,
            quotedSnippets: $quotedSnippets,
            relevanceScore: $chunk->relevanceScore,
        );
    }

    public function getLineRange(): string
    {
        return $this->startLine === $this->endLine
            ? "L{$this->startLine}"
            : "L{$this->startLine}-{$this->endLine}";
    }
}
