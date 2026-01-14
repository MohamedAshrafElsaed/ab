<?php

namespace App\Services\AskAI\DTO;

readonly class RetrievedChunk
{
    public function __construct(
        public string $chunkId,
        public string $path,
        public int $startLine,
        public int $endLine,
        public string $sha1,
        public string $content,
        public float $relevanceScore,
        public array $matchedKeywords = [],
        public array $symbolsDeclared = [],
        public array $imports = [],
        public ?string $language = null,
        public bool $isCompleteFile = false,
    ) {}

    public function toArray(): array
    {
        return [
            'chunk_id' => $this->chunkId,
            'path' => $this->path,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
            'sha1' => $this->sha1,
            'content' => $this->content,
            'relevance_score' => $this->relevanceScore,
            'matched_keywords' => $this->matchedKeywords,
            'symbols_declared' => $this->symbolsDeclared,
            'imports' => $this->imports,
            'language' => $this->language,
            'is_complete_file' => $this->isCompleteFile,
        ];
    }

    public function getLineCount(): int
    {
        return $this->endLine - $this->startLine + 1;
    }

    public function getContentLength(): int
    {
        return strlen($this->content);
    }

    public static function fromDatabaseRow(array $row, string $content, float $score, array $matchedKeywords = []): self
    {
        return new self(
            chunkId: $row['chunk_id'],
            path: $row['path'],
            startLine: (int) $row['start_line'],
            endLine: (int) $row['end_line'],
            sha1: $row['sha1'] ?? $row['chunk_sha1'] ?? '',
            content: $content,
            relevanceScore: $score,
            matchedKeywords: $matchedKeywords,
            symbolsDeclared: is_string($row['symbols_declared'] ?? null)
                ? json_decode($row['symbols_declared'], true) ?? []
                : ($row['symbols_declared'] ?? []),
            imports: is_string($row['imports'] ?? null)
                ? json_decode($row['imports'], true) ?? []
                : ($row['imports'] ?? []),
            language: $row['language'] ?? null,
            isCompleteFile: (bool) ($row['is_complete_file'] ?? false),
        );
    }
}
