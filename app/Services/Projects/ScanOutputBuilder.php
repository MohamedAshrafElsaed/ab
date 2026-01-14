<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Services\Projects\Concerns\HasDeterministicChunkId;

/**
 * @deprecated Use KnowledgeBaseBuilder instead for standardized output.
 *
 * This class is kept for backwards compatibility with existing scan_output.json files.
 * New scans should use KnowledgeBaseBuilder which produces:
 * - scan_meta.json
 * - files_index.json/.ndjson
 * - chunks.ndjson
 * - directory_stats.json
 */
class ScanOutputBuilder
{
    use HasDeterministicChunkId;
    private Project $project;
    private ExclusionMatcher $exclusionMatcher;
    private int $startTime;

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->exclusionMatcher = new ExclusionMatcher($project);
        $this->startTime = (int) (microtime(true) * 1000);
    }

    /**
     * Build legacy scan output format.
     *
     * @deprecated Use KnowledgeBaseBuilder::build() instead.
     */
    public function build(): array
    {
        $files = $this->project->files()->get();
        $chunks = $this->project->chunks()->orderBy('path')->orderBy('start_line')->get();

        // Build file_to_chunks mapping using CURRENT chunk IDs (not legacy)
        $fileToChunks = [];
        foreach ($chunks as $chunk) {
            if (!isset($fileToChunks[$chunk->path])) {
                $fileToChunks[$chunk->path] = [];
            }
            $fileToChunks[$chunk->path][] = $chunk->chunk_id;
        }

        $endTime = (int) (microtime(true) * 1000);

        return [
            'scan_meta' => $this->buildScanMeta(),
            'stats' => $this->buildStats($files, $chunks, $endTime - $this->startTime),
            'files' => $this->buildFileRecords($files, $fileToChunks),
            'chunks' => $this->buildChunkRecords($chunks),
            'file_to_chunks' => $fileToChunks,
        ];
    }

    private function buildScanMeta(): array
    {
        $scanId = sha1(
            $this->project->id .
            $this->project->last_commit_sha .
            now()->toIso8601String()
        );

        return [
            'scan_id' => $scanId,
            'project_id' => $this->project->id,
            'repo_full_name' => $this->project->repo_full_name,
            'default_branch' => $this->project->default_branch,
            'selected_branch' => $this->project->selected_branch ?? $this->project->default_branch,
            'head_commit_sha' => $this->project->last_commit_sha,
            'parent_commit_sha' => $this->project->parent_commit_sha ?? null,
            'scanned_at_iso' => now()->toIso8601String(),
            'scanner_version' => '2.1.0',
            'exclusion_rules_version' => $this->exclusionMatcher->getRulesVersion(),
            'is_incremental' => false,
            'previous_scan_id' => null,
            '_deprecated' => 'Use KnowledgeBaseBuilder for new implementations',
        ];
    }

    private function buildStats($files, $chunks, int $durationMs): array
    {
        $scannedFiles = $files->where('is_excluded', false);
        $excludedFiles = $files->where('is_excluded', true);
        $binaryFiles = $files->where('is_binary', true);

        // Group by extension
        $byExtension = [];
        foreach ($scannedFiles as $file) {
            $ext = $file->extension ?: 'no_extension';
            if (!isset($byExtension[$ext])) {
                $byExtension[$ext] = ['files' => 0, 'lines' => 0, 'bytes' => 0];
            }
            $byExtension[$ext]['files']++;
            $byExtension[$ext]['lines'] += $file->line_count ?? 0;
            $byExtension[$ext]['bytes'] += $file->size_bytes ?? 0;
        }

        // Group by directory
        $byDirectory = [];
        foreach ($scannedFiles as $file) {
            $dir = dirname($file->path);
            if ($dir === '.') {
                $dir = '(root)';
            }

            if (!isset($byDirectory[$dir])) {
                $byDirectory[$dir] = [
                    'directory' => $dir,
                    'file_count' => 0,
                    'total_lines' => 0,
                    'total_bytes' => 0,
                    'depth' => $dir === '(root)' ? 0 : substr_count($dir, '/') + 1,
                ];
            }
            $byDirectory[$dir]['file_count']++;
            $byDirectory[$dir]['total_lines'] += $file->line_count ?? 0;
            $byDirectory[$dir]['total_bytes'] += $file->size_bytes ?? 0;
        }

        ksort($byDirectory);

        return [
            'total_files_scanned' => $scannedFiles->count(),
            'total_files_excluded' => $excludedFiles->count(),
            'total_files_binary' => $binaryFiles->count(),
            'total_lines' => $scannedFiles->sum('line_count'),
            'total_bytes' => $scannedFiles->sum('size_bytes'),
            'total_chunks' => $chunks->count(),
            'scan_duration_ms' => $durationMs,
            'by_extension' => $byExtension,
            'by_directory' => array_values($byDirectory),
        ];
    }

    private function buildFileRecords($files, array $fileToChunks): array
    {
        return $files->map(function ($file) use ($fileToChunks) {
            return [
                'file_id' => $file->file_id ?? ('f_' . substr(sha1($file->path), 0, 12)),
                'project_id' => $this->project->id,
                'repo_full_name' => $this->project->repo_full_name,
                'default_branch' => $this->project->default_branch,
                'selected_branch' => $this->project->selected_branch ?? $this->project->default_branch,
                'head_commit_sha' => $this->project->last_commit_sha,
                'scanned_at_iso' => now()->toIso8601String(),
                'file_path' => $file->path,
                'file_name' => basename($file->path),
                'directory' => dirname($file->path) === '.' ? '(root)' : dirname($file->path),
                'extension' => $file->extension,
                'language' => $file->language ?? 'plaintext',
                'file_bytes' => $file->size_bytes,
                'total_lines' => $file->line_count,
                'file_sha1' => $file->sha1,
                'file_modified_at' => $file->file_modified_at,
                'is_binary' => (bool) $file->is_binary,
                'is_excluded' => (bool) $file->is_excluded,
                'exclusion_reason' => $file->exclusion_reason,
                'framework_hints' => $file->framework_hints ?? [],
                'symbols_declared' => $file->symbols_declared ?? [],
                'imports' => $file->imports ?? [],
                'chunk_count' => count($fileToChunks[$file->path] ?? []),
                'chunk_ids' => $fileToChunks[$file->path] ?? [],
            ];
        })->toArray();
    }

    private function buildChunkRecords($chunks): array
    {
        return $chunks->map(function ($chunk) {
            return [
                'chunk_id' => $chunk->chunk_id,
                'file_path' => $chunk->path,
                'file_sha1' => $chunk->sha1,
                'start_line' => $chunk->start_line,
                'end_line' => $chunk->end_line,
                'chunk_index' => $chunk->chunk_index ?? 0,
                'is_complete_file' => (bool) ($chunk->is_complete_file ?? false),
                'chunk_bytes' => $chunk->chunk_size_bytes,
                'chunk_lines' => $chunk->end_line - $chunk->start_line + 1,
                'chunk_sha1' => $chunk->chunk_sha1,
                'content' => $this->getChunkContent($chunk),
                'symbols_declared' => $chunk->symbols_declared ?? [],
                'symbols_used' => $chunk->symbols_used ?? [],
                'imports' => $chunk->imports ?? [],
                'references' => $chunk->references ?? [],
            ];
        })->toArray();
    }

    private function getChunkContent($chunk): ?string
    {
        $fullPath = $this->project->repo_path . '/' . $chunk->path;

        if (!file_exists($fullPath)) {
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $lines = explode("\n", $content);
        $chunkLines = array_slice($lines, $chunk->start_line - 1, $chunk->end_line - $chunk->start_line + 1);

        return implode("\n", $chunkLines);
    }
}
