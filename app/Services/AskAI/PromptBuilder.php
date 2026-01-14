<?php

namespace App\Services\AskAI;

use App\Models\Project;
use App\Services\AskAI\DTO\RetrievedChunk;

class PromptBuilder
{
    private const string SYSTEM_PROMPT = <<<'PROMPT'
        You are a code analysis assistant. Your task is to answer questions about a codebase based ONLY on the provided code chunks.

        ## CRITICAL RULES:
        1. **GROUNDED ANSWERS ONLY**: Only answer based on the code chunks provided below. Never speculate about code not present.
        2. **CITE YOUR SOURCES**: When referencing code, always cite the file path and line numbers.
        3. **ADMIT LIMITATIONS**: If the provided chunks don't contain enough information, explicitly say "NOT ENOUGH CONTEXT" and list what files/information would be needed.
        4. **NO FABRICATION**: Never invent function names, class names, or code patterns not shown in the chunks.

        ## RESPONSE FORMAT:
        Your response must be in markdown with the following structure:

        ### Answer
        [Your grounded answer here with inline citations like `path/to/file.php:L10-20`]

        ### Referenced Files
        - `file1.php` (lines X-Y): Brief description of what was found
        - `file2.php` (lines X-Y): Brief description of what was found

        ### Limitations
        [What cannot be determined from the provided context]

        ## PROJECT CONTEXT:
        - Repository: {{REPO_NAME}}
        - Framework: {{FRAMEWORK}}
        - Stack: {{STACK_INFO}}
    PROMPT;

    public function buildSystemPrompt(Project $project): string
    {
        $stack = $project->stack_info ?? [];
        $stackInfo = [];

        if (isset($stack['framework'])) {
            $stackInfo[] = $stack['framework'] . ($stack['framework_version'] ?? '');
        }
        if (!empty($stack['frontend'])) {
            $stackInfo[] = 'Frontend: ' . implode(', ', $stack['frontend']);
        }
        if (!empty($stack['database'])) {
            $stackInfo[] = 'Database: ' . implode(', ', $stack['database']);
        }

        return str_replace(
            ['{{REPO_NAME}}', '{{FRAMEWORK}}', '{{STACK_INFO}}'],
            [
                $project->repo_full_name,
                $stack['framework'] ?? 'Unknown',
                implode(' | ', $stackInfo) ?: 'Not detected',
            ],
            self::SYSTEM_PROMPT
        );
    }

    /**
     * Build the user prompt with retrieved chunks.
     *
     * @param RetrievedChunk[] $chunks
     */
    public function buildUserPrompt(string $question, array $chunks, array $queryAnalysis = []): string
    {
        $prompt = "## USER QUESTION:\n{$question}\n\n";

        if (!empty($queryAnalysis['keywords'])) {
            $prompt .= "## DETECTED KEYWORDS:\n";
            $prompt .= implode(', ', array_map('strval', $queryAnalysis['keywords'])) . "\n\n";
        }

        $prompt .= "## CODE CHUNKS FROM REPOSITORY:\n\n";

        if (empty($chunks)) {
            $prompt .= "*No relevant code chunks were found for this query.*\n\n";
            $prompt .= "Please respond with 'NOT ENOUGH CONTEXT' and suggest what files or information would help answer this question.\n";
            return $prompt;
        }

        foreach ($chunks as $index => $chunk) {
            $chunkNum = $index + 1;
            $prompt .= "### CHUNK {$chunkNum}: `{$chunk->path}` (Lines {$chunk->startLine}-{$chunk->endLine})\n";

            if (!empty($chunk->matchedKeywords)) {
                $keywords = $this->flattenToStrings($chunk->matchedKeywords);
                if (!empty($keywords)) {
                    $prompt .= "**Matched**: " . implode(', ', $keywords) . "\n";
                }
            }

            if (!empty($chunk->symbolsDeclared)) {
                $symbols = $this->flattenToStrings($chunk->symbolsDeclared);
                if (!empty($symbols)) {
                    $prompt .= "**Declares**: " . implode(', ', array_slice($symbols, 0, 10)) . "\n";
                }
            }

            $prompt .= "```" . ($chunk->language ?? '') . "\n";
            $prompt .= $chunk->content;
            $prompt .= "\n```\n\n";
        }

        $prompt .= "---\n\n";
        $prompt .= "Remember:\n";
        $prompt .= "1. Answer ONLY based on the chunks above\n";
        $prompt .= "2. Cite specific files and line numbers\n";
        $prompt .= "3. If information is missing, say 'NOT ENOUGH CONTEXT'\n";

        return $prompt;
    }

    /**
     * Flatten mixed array to string array.
     */
    private function flattenToStrings(mixed $data): array
    {
        if (!is_array($data)) {
            return is_string($data) ? [$data] : [(string) $data];
        }

        $result = [];
        array_walk_recursive($data, function ($item) use (&$result) {
            if (is_string($item)) {
                $result[] = $item;
            } elseif (is_scalar($item)) {
                $result[] = (string) $item;
            }
        });

        return $result;
    }

    /**
     * Build a prompt for analyzing confidence in the response.
     */
    public function buildConfidenceAnalysisPrompt(string $answer, array $chunks): string
    {
        $count = count($chunks);
        return <<<PROMPT
            Based on the following answer and the code chunks used to generate it, determine the confidence level.

            ## ANSWER:
            {$answer}

            ## CHUNKS USED: {$count} files

            ## CONFIDENCE CRITERIA:
            - HIGH: Answer is directly supported by code, all key points are cited
            - MEDIUM: Answer is partially supported, some inference required
            - LOW: Answer relies heavily on inference, missing key context

            Respond with exactly one word: HIGH, MEDIUM, or LOW
        PROMPT;
    }

    /**
     * Extract snippets that were likely quoted in the answer.
     *
     * @param RetrievedChunk[] $chunks
     * @return array<string, string[]> Map of chunk_id => quoted snippets
     */
    public function extractQuotedSnippets(string $answer, array $chunks): array
    {
        $snippets = [];

        foreach ($chunks as $chunk) {
            $chunkSnippets = [];
            $contentLines = explode("\n", $chunk->content);

            // Check if specific lines are referenced in the answer
            $pathPattern = preg_quote($chunk->path, '/');
            if (preg_match("/{$pathPattern}.*?L?(\d+)/i", $answer, $lineMatch)) {
                $lineNum = (int) $lineMatch[1];
                $relativeIndex = $lineNum - $chunk->startLine;

                if ($relativeIndex >= 0 && $relativeIndex < count($contentLines)) {
                    $snippetStart = max(0, $relativeIndex - 1);
                    $snippetEnd = min(count($contentLines), $relativeIndex + 2);
                    $snippet = implode("\n", array_slice($contentLines, $snippetStart, $snippetEnd - $snippetStart));
                    if (strlen($snippet) > 10) {
                        $chunkSnippets[] = $snippet;
                    }
                }
            }

            // Check for code blocks that match content from this chunk
            preg_match_all('/```(?:\w+)?\n(.+?)\n```/s', $answer, $codeBlocks);
            foreach ($codeBlocks[1] ?? [] as $block) {
                $blockTrimmed = trim($block);
                if (strlen($blockTrimmed) > 20 && str_contains($chunk->content, $blockTrimmed)) {
                    $chunkSnippets[] = $blockTrimmed;
                }
            }

            // Look for inline code that matches symbols
            preg_match_all('/`([^`]+)`/', $answer, $inlineCode);
            $symbols = $this->flattenToStrings($chunk->symbolsDeclared);
            foreach ($inlineCode[1] ?? [] as $code) {
                foreach ($symbols as $symbol) {
                    if (strcasecmp($code, $symbol) === 0) {
                        // Find the line declaring this symbol
                        foreach ($contentLines as $i => $line) {
                            if (str_contains($line, $symbol)) {
                                $chunkSnippets[] = trim($line);
                                break;
                            }
                        }
                        break;
                    }
                }
            }

            if (!empty($chunkSnippets)) {
                $snippets[$chunk->chunkId] = array_slice(array_unique($chunkSnippets), 0, 3);
            }
        }

        return $snippets;
    }
}
