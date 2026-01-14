<?php

namespace App\Services\AskAI\DTO;

readonly class AskAIResponse
{
    public function __construct(
        public string $answerMarkdown,
        public array $auditLog,
        public array $missingDetails,
        public string $confidence,
        public int $chunksUsed = 0,
        public int $totalContentLength = 0,
        public ?string $processingNote = null,
    ) {}

    public function toArray(): array
    {
        return [
            'answer_markdown' => $this->answerMarkdown,
            'audit_log' => array_map(fn(AuditLogEntry $e) => $e->toArray(), $this->auditLog),
            'missing_details' => $this->missingDetails,
            'confidence' => $this->confidence,
            'meta' => [
                'chunks_used' => $this->chunksUsed,
                'total_content_length' => $this->totalContentLength,
                'processing_note' => $this->processingNote,
            ],
        ];
    }

    public static function notEnoughContext(array $missingFiles = [], ?string $reason = null): self
    {
        $message = "## NOT ENOUGH CONTEXT\n\n";
        $message .= $reason ?? "I don't have enough information in the scanned codebase to answer this question accurately.";

        if (!empty($missingFiles)) {
            $message .= "\n\n### Files/Information that might help:\n";
            foreach ($missingFiles as $file) {
                $message .= "- `{$file}`\n";
            }
        }

        $message .= "\n\n*Please ensure the relevant files are included in the project scan.*";

        return new self(
            answerMarkdown: $message,
            auditLog: [],
            missingDetails: $missingFiles,
            confidence: 'low',
            processingNote: 'Insufficient context to provide a grounded answer',
        );
    }

    public static function error(string $message): self
    {
        return new self(
            answerMarkdown: "## Error\n\n{$message}",
            auditLog: [],
            missingDetails: [],
            confidence: 'low',
            processingNote: 'Error occurred during processing',
        );
    }

    public function isSuccessful(): bool
    {
        return $this->confidence !== 'low' || !empty($this->auditLog);
    }
}
