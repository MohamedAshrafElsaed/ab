<?php

namespace App\Services\AskAI;

class SensitiveContentRedactor
{
    /** @var array<string> */
    private array $patterns;

    private string $replacement;

    private bool $enabled;

    /** @var array<string> */
    private array $sensitiveFilenames = [
        '.env',
        '.env.local',
        '.env.production',
        '.env.staging',
        '.env.development',
        'credentials.json',
        'secrets.json',
        'service-account.json',
        '.npmrc',
        '.pypirc',
        'id_rsa',
        'id_ed25519',
        '.pem',
        '.key',
    ];

    public function __construct()
    {
        $config = config('askai.redaction', []);
        $this->enabled = $config['enabled'] ?? true;
        $this->patterns = $config['patterns'] ?? [];
        $this->replacement = $config['replacement'] ?? '[REDACTED]';
    }

    public function redact(string $content, ?string $filePath = null): string
    {
        if (!$this->enabled) {
            return $content;
        }

        // Check if entire file should be redacted
        if ($filePath && $this->isSensitiveFile($filePath)) {
            return $this->redactEntireContent($content, $filePath);
        }

        // Apply pattern-based redaction
        foreach ($this->patterns as $pattern) {
            $content = preg_replace_callback($pattern, function ($matches) {
                // Keep the key/label but redact the value
                if (isset($matches[1])) {
                    $full = $matches[0];
                    $value = $matches[1];
                    return str_replace($value, $this->replacement, $full);
                }
                return $this->replacement;
            }, $content) ?? $content;
        }

        // Redact common inline patterns
        $content = $this->redactInlineSecrets($content);

        return $content;
    }

    public function redactChunks(array $chunks): array
    {
        return array_map(function ($chunk) {
            if (is_array($chunk) && isset($chunk['content'])) {
                $chunk['content'] = $this->redact($chunk['content'], $chunk['path'] ?? null);
            }
            return $chunk;
        }, $chunks);
    }

    public function isSensitiveFile(string $filePath): bool
    {
        $filename = basename($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Check exact filename matches
        foreach ($this->sensitiveFilenames as $sensitive) {
            if (strcasecmp($filename, $sensitive) === 0) {
                return true;
            }
            // Check if filename ends with sensitive pattern (e.g., file.pem)
            if (str_starts_with($sensitive, '.') && str_ends_with(strtolower($filename), strtolower($sensitive))) {
                return true;
            }
        }

        // Check path contains secrets directory
        if (preg_match('/\/(secrets?|credentials?|private|keys?)\//i', $filePath)) {
            return true;
        }

        return false;
    }

    private function redactEntireContent(string $content, string $filePath): string
    {
        $filename = basename($filePath);
        $lineCount = substr_count($content, "\n") + 1;

        return "# Content of '{$filename}' has been redacted for security.\n" .
            "# This file appears to contain sensitive information.\n" .
            "# Original file had approximately {$lineCount} lines.\n" .
            "# If you need to reference this file's structure, please review it directly in your codebase.";
    }

    private function redactInlineSecrets(string $content): string
    {
        // Redact hex strings that look like API keys (32+ chars)
        $content = preg_replace(
            '/([\'"])[a-f0-9]{32,}([\'"])/i',
            '$1' . $this->replacement . '$2',
            $content
        ) ?? $content;

        // Redact base64 strings that look like tokens (40+ chars)
        $content = preg_replace(
            '/([\'"])[A-Za-z0-9+\/]{40,}={0,2}([\'"])/i',
            '$1' . $this->replacement . '$2',
            $content
        ) ?? $content;

        return $content;
    }

    public function getSensitivePatterns(): array
    {
        return $this->patterns;
    }

    public function addPattern(string $pattern): void
    {
        $this->patterns[] = $pattern;
    }
}
