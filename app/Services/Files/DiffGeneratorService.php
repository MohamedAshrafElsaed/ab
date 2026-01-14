<?php

namespace App\Services\Files;

/**
 * Service for generating and parsing diffs.
 */
class DiffGeneratorService
{
    private int $contextLines;

    public function __construct(int $contextLines = 3)
    {
        $this->contextLines = $contextLines;
    }

    /**
     * Generate a unified diff between two contents.
     */
    public function generateDiff(string $original, string $modified, string $fileName = 'file'): string
    {
        $originalLines = explode("\n", $original);
        $modifiedLines = explode("\n", $modified);

        $diff = $this->computeDiff($originalLines, $modifiedLines);

        if (empty($diff)) {
            return '';
        }

        $output = [];
        $output[] = "--- a/{$fileName}";
        $output[] = "+++ b/{$fileName}";

        $chunks = $this->groupDiffIntoChunks($diff, count($originalLines), count($modifiedLines));

        foreach ($chunks as $chunk) {
            $output[] = $chunk['header'];
            foreach ($chunk['lines'] as $line) {
                $output[] = $line;
            }
        }

        return implode("\n", $output);
    }

    /**
     * Parse a unified diff into structured changes.
     *
     * @return array<array{type: string, old_line: ?int, new_line: ?int, content: string}>
     */
    public function parseDiff(string $diff): array
    {
        $lines = explode("\n", $diff);
        $changes = [];
        $oldLine = 0;
        $newLine = 0;

        foreach ($lines as $line) {
            if (str_starts_with($line, '@@')) {
                if (preg_match('/@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $matches)) {
                    $oldLine = (int) $matches[1];
                    $newLine = (int) $matches[2];
                }
                $changes[] = [
                    'type' => 'header',
                    'old_line' => null,
                    'new_line' => null,
                    'content' => $line,
                ];
            } elseif (str_starts_with($line, '---') || str_starts_with($line, '+++')) {
                continue;
            } elseif (str_starts_with($line, '+')) {
                $changes[] = [
                    'type' => 'added',
                    'old_line' => null,
                    'new_line' => $newLine++,
                    'content' => substr($line, 1),
                ];
            } elseif (str_starts_with($line, '-')) {
                $changes[] = [
                    'type' => 'removed',
                    'old_line' => $oldLine++,
                    'new_line' => null,
                    'content' => substr($line, 1),
                ];
            } elseif (str_starts_with($line, ' ') || $line === '') {
                $changes[] = [
                    'type' => 'context',
                    'old_line' => $oldLine++,
                    'new_line' => $newLine++,
                    'content' => $line === '' ? '' : substr($line, 1),
                ];
            }
        }

        return $changes;
    }

    /**
     * Generate HTML-formatted diff for display.
     */
    public function generateHtmlDiff(string $diff): string
    {
        $changes = $this->parseDiff($diff);
        $html = '<div class="diff">';

        foreach ($changes as $change) {
            $content = htmlspecialchars($change['content']);
            $lineNum = '';

            if ($change['type'] === 'header') {
                $html .= "<div class=\"diff-header\">{$content}</div>";
            } elseif ($change['type'] === 'added') {
                $lineNum = $change['new_line'] ? "<span class=\"line-num\">+{$change['new_line']}</span>" : '';
                $html .= "<div class=\"diff-added\">{$lineNum}<span class=\"content\">+{$content}</span></div>";
            } elseif ($change['type'] === 'removed') {
                $lineNum = $change['old_line'] ? "<span class=\"line-num\">-{$change['old_line']}</span>" : '';
                $html .= "<div class=\"diff-removed\">{$lineNum}<span class=\"content\">-{$content}</span></div>";
            } else {
                $html .= "<div class=\"diff-context\"><span class=\"content\"> {$content}</span></div>";
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Get diff statistics.
     *
     * @return array{added: int, removed: int, changed_hunks: int}
     */
    public function getStats(string $diff): array
    {
        $changes = $this->parseDiff($diff);

        return [
            'added' => count(array_filter($changes, fn($c) => $c['type'] === 'added')),
            'removed' => count(array_filter($changes, fn($c) => $c['type'] === 'removed')),
            'changed_hunks' => count(array_filter($changes, fn($c) => $c['type'] === 'header')),
        ];
    }

    /**
     * Compute the diff using Myers algorithm (simplified).
     *
     * @param array<string> $original
     * @param array<string> $modified
     * @return array<array{type: string, line: string, old_index: ?int, new_index: ?int}>
     */
    private function computeDiff(array $original, array $modified): array
    {
        $n = count($original);
        $m = count($modified);

        // Build LCS table
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                if ($original[$i - 1] === $modified[$j - 1]) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }

        // Backtrack to find diff
        $diff = [];
        $i = $n;
        $j = $m;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $original[$i - 1] === $modified[$j - 1]) {
                array_unshift($diff, [
                    'type' => 'context',
                    'line' => $original[$i - 1],
                    'old_index' => $i - 1,
                    'new_index' => $j - 1,
                ]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                array_unshift($diff, [
                    'type' => 'added',
                    'line' => $modified[$j - 1],
                    'old_index' => null,
                    'new_index' => $j - 1,
                ]);
                $j--;
            } elseif ($i > 0) {
                array_unshift($diff, [
                    'type' => 'removed',
                    'line' => $original[$i - 1],
                    'old_index' => $i - 1,
                    'new_index' => null,
                ]);
                $i--;
            }
        }

        return $diff;
    }

    /**
     * Group diff entries into chunks with headers.
     *
     * @return array<array{header: string, lines: array<string>}>
     */
    private function groupDiffIntoChunks(array $diff, int $originalTotal, int $modifiedTotal): array
    {
        $chunks = [];
        $currentChunk = null;
        $contextBuffer = [];

        foreach ($diff as $index => $entry) {
            if ($entry['type'] === 'context') {
                if ($currentChunk !== null) {
                    $contextBuffer[] = $entry;

                    if (count($contextBuffer) > $this->contextLines * 2) {
                        // End current chunk
                        for ($i = 0; $i < $this->contextLines && $i < count($contextBuffer); $i++) {
                            $currentChunk['lines'][] = ' ' . $contextBuffer[$i]['line'];
                        }
                        $this->finalizeChunk($chunks, $currentChunk);
                        $currentChunk = null;
                        $contextBuffer = array_slice($contextBuffer, -$this->contextLines);
                    }
                } else {
                    $contextBuffer[] = $entry;
                    if (count($contextBuffer) > $this->contextLines) {
                        array_shift($contextBuffer);
                    }
                }
            } else {
                if ($currentChunk === null) {
                    $startOld = ($entry['old_index'] ?? $entry['new_index'] ?? 0) - count($contextBuffer) + 1;
                    $startNew = ($entry['new_index'] ?? $entry['old_index'] ?? 0) - count($contextBuffer) + 1;

                    $currentChunk = [
                        'start_old' => max(1, $startOld),
                        'start_new' => max(1, $startNew),
                        'count_old' => 0,
                        'count_new' => 0,
                        'lines' => [],
                    ];

                    foreach ($contextBuffer as $ctx) {
                        $currentChunk['lines'][] = ' ' . $ctx['line'];
                        $currentChunk['count_old']++;
                        $currentChunk['count_new']++;
                    }
                } else {
                    foreach ($contextBuffer as $ctx) {
                        $currentChunk['lines'][] = ' ' . $ctx['line'];
                        $currentChunk['count_old']++;
                        $currentChunk['count_new']++;
                    }
                }

                $contextBuffer = [];

                if ($entry['type'] === 'added') {
                    $currentChunk['lines'][] = '+' . $entry['line'];
                    $currentChunk['count_new']++;
                } else {
                    $currentChunk['lines'][] = '-' . $entry['line'];
                    $currentChunk['count_old']++;
                }
            }
        }

        if ($currentChunk !== null) {
            for ($i = 0; $i < $this->contextLines && $i < count($contextBuffer); $i++) {
                $currentChunk['lines'][] = ' ' . $contextBuffer[$i]['line'];
                $currentChunk['count_old']++;
                $currentChunk['count_new']++;
            }
            $this->finalizeChunk($chunks, $currentChunk);
        }

        return $chunks;
    }

    private function finalizeChunk(array &$chunks, array $chunk): void
    {
        $header = sprintf(
            '@@ -%d,%d +%d,%d @@',
            $chunk['start_old'],
            $chunk['count_old'],
            $chunk['start_new'],
            $chunk['count_new']
        );

        $chunks[] = [
            'header' => $header,
            'lines' => $chunk['lines'],
        ];
    }
}
