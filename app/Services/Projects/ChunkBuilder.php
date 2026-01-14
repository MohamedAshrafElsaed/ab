<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFileChunk;
use App\Services\Projects\Concerns\HasDeterministicChunkId;

class ChunkBuilder
{
    use HasDeterministicChunkId;
    private int $maxChunkBytes;
    private int $maxChunkLines;
    private int $minChunkLines;
    private ExclusionMatcher $exclusionMatcher;
    private LanguageDetector $languageDetector;
    private SymbolExtractor $symbolExtractor;

    private array $priorityDirs = [
        'app',
        'routes',
        'config',
        'database/migrations',
        'resources/views',
        'resources/js',
        'resources/css',
        'tests',
    ];

    public function __construct()
    {
        $config = config('projects.chunking', []);
        $this->maxChunkBytes = $config['max_bytes'] ?? 200 * 1024;
        $this->maxChunkLines = $config['max_lines'] ?? 400; // Default 400 per requirements
        $this->minChunkLines = $config['min_lines'] ?? 250; // Target 250-400 range
        $this->languageDetector = new LanguageDetector();
        $this->symbolExtractor = new SymbolExtractor();
    }

    public function build(Project $project, ?callable $progressCallback = null): array
    {
        $this->exclusionMatcher = new ExclusionMatcher($project);

        // Clear existing chunks
        $project->chunks()->delete();
        $this->clearChunkFiles($project);

        $files = $project->files()
            ->where('is_binary', false)
            ->where('is_excluded', false)
            ->where('size_bytes', '>', 0)
            ->where('size_bytes', '<=', config('projects.max_file_size', 1024 * 1024))
            ->orderByRaw($this->getDirectoryPriorityOrder())
            ->get();

        $totalFiles = $files->count();
        $processed = 0;
        $allChunks = [];
        $fileToChunks = [];

        foreach ($files as $file) {
            $filePath = $project->repo_path . '/' . $file->path;

            if (!file_exists($filePath)) {
                $processed++;
                continue;
            }

            $content = @file_get_contents($filePath);
            if ($content === false) {
                $processed++;
                continue;
            }

            // Ensure file SHA1 is current
            $currentSha1 = sha1($content);
            if ($file->sha1 !== $currentSha1) {
                $file->update(['sha1' => $currentSha1]);
            }

            $chunks = $this->chunkFile($project, $file, $content);

            foreach ($chunks as $chunk) {
                $allChunks[] = $chunk;
                $fileToChunks[$file->path][] = $chunk['chunk_id'];
            }

            $processed++;

            if ($progressCallback && $processed % 50 === 0) {
                $progressCallback($processed, $totalFiles);
            }
        }

        // Save all chunks to database
        $this->saveChunks($project, $allChunks);

        // Save path index (legacy format for backwards compat)
        $this->savePathIndex($project, $fileToChunks);

        // Save manifest and directories
        $this->saveManifest($project);
        $this->saveDirectories($project);

        return [
            'total_chunks' => count($allChunks),
            'file_to_chunks' => $fileToChunks,
            'exclusion_rules_version' => $this->exclusionMatcher->getRulesVersion(),
        ];
    }

    private function chunkFile(Project $project, ProjectFile $file, string $content): array
    {
        $lines = explode("\n", $content);
        $totalLines = count($lines);
        $fileSize = strlen($content);
        $fileSha1 = $file->sha1 ?? sha1($content);

        $chunks = [];

        // Single chunk for small files (within max limits)
        if ($totalLines <= $this->maxChunkLines && $fileSize <= $this->maxChunkBytes) {
            $chunkContent = $content;
            $chunkSha1 = sha1($chunkContent);

            // Use deterministic chunk ID (matches KnowledgeBaseBuilder)
            $chunkId = $this->generateChunkId($file->path, $fileSha1, 1, $totalLines);

            $chunks[] = [
                'chunk_id' => $chunkId,
                'file_path' => $file->path,
                'file_sha1' => $fileSha1,
                'start_line' => 1,
                'end_line' => $totalLines,
                'chunk_index' => 0,
                'is_complete_file' => true,
                'chunk_bytes' => $fileSize,
                'chunk_lines' => $totalLines,
                'chunk_sha1' => $chunkSha1,
                'content' => $chunkContent,
                'symbols_declared' => $this->symbolExtractor->extractDeclarations($content, $file->extension),
                'symbols_used' => $this->symbolExtractor->extractUsages($content, $file->extension),
                'imports' => $this->symbolExtractor->extractImports($content, $file->extension),
                'references' => [],
            ];

            return $chunks;
        }

        // Split large files
        $segments = $this->splitLargeFile($lines, $file->path);

        foreach ($segments as $index => $segment) {
            $chunkLines = array_slice($lines, $segment['start'] - 1, $segment['end'] - $segment['start'] + 1);
            $chunkContent = implode("\n", $chunkLines);
            $chunkSha1 = sha1($chunkContent);

            // Use deterministic chunk ID
            $chunkId = $this->generateChunkId($file->path, $fileSha1, $segment['start'], $segment['end']);

            $chunks[] = [
                'chunk_id' => $chunkId,
                'file_path' => $file->path,
                'file_sha1' => $fileSha1,
                'start_line' => $segment['start'],
                'end_line' => $segment['end'],
                'chunk_index' => $index,
                'is_complete_file' => false,
                'chunk_bytes' => strlen($chunkContent),
                'chunk_lines' => count($chunkLines),
                'chunk_sha1' => $chunkSha1,
                'content' => $chunkContent,
                'symbols_declared' => $this->symbolExtractor->extractDeclarations($chunkContent, $file->extension),
                'symbols_used' => $this->symbolExtractor->extractUsages($chunkContent, $file->extension),
                'imports' => $this->symbolExtractor->extractImports($chunkContent, $file->extension),
                'references' => [],
            ];
        }

        return $chunks;
    }

    // generateChunkId() is provided by HasDeterministicChunkId trait

    private function splitLargeFile(array $lines, string $path): array
    {
        $segments = [];
        $totalLines = count($lines);
        $currentStart = 1;

        while ($currentStart <= $totalLines) {
            // Target chunk size between min and max
            $targetEnd = min($currentStart + $this->maxChunkLines - 1, $totalLines);

            // Try to find a good break point if we're not at the end
            if ($targetEnd < $totalLines) {
                $minEnd = $currentStart + $this->minChunkLines - 1;
                $breakPoint = $this->findBreakPoint($lines, $minEnd - 1, $targetEnd - 1);

                if ($breakPoint !== null && $breakPoint >= $minEnd - 1) {
                    $targetEnd = $breakPoint + 1;
                }
            }

            $segments[] = [
                'start' => $currentStart,
                'end' => $targetEnd,
            ];

            $currentStart = $targetEnd + 1;
        }

        return $segments;
    }

    private function findBreakPoint(array $lines, int $searchStart, int $searchEnd): ?int
    {
        $weights = config('projects.chunking.break_weights', [
            'empty_line' => 10,
            'function_boundary' => 8,
            'class_boundary' => 9,
            'block_end' => 7,
        ]);

        $bestPoint = null;
        $bestWeight = 0;

        // Search from end backwards to searchStart
        for ($i = $searchEnd; $i >= $searchStart; $i--) {
            $line = trim($lines[$i] ?? '');
            $weight = 0;

            // Empty line
            if (empty($line)) {
                $weight = $weights['empty_line'];
            }
            // Function/class boundaries
            elseif (preg_match('/^(function|class|trait|interface)\s/', $line)) {
                $weight = $weights['class_boundary'];
            }
            elseif (preg_match('/^(public|private|protected)\s+(function|static)/', $line)) {
                $weight = $weights['function_boundary'];
            }
            // Block end
            elseif (preg_match('/^\}\s*$/', $line)) {
                $weight = $weights['block_end'];
            }

            if ($weight > $bestWeight) {
                $bestWeight = $weight;
                $bestPoint = $i;
            }
        }

        return $bestPoint;
    }

    private function saveChunks(Project $project, array $chunks): void
    {
        // Batch insert chunk records
        $records = [];

        foreach ($chunks as $chunk) {
            $records[] = [
                'project_id' => $project->id,
                'chunk_id' => $chunk['chunk_id'],
                'path' => $chunk['file_path'],
                'start_line' => $chunk['start_line'],
                'end_line' => $chunk['end_line'],
                'chunk_index' => $chunk['chunk_index'],
                'sha1' => $chunk['file_sha1'],
                'chunk_sha1' => $chunk['chunk_sha1'],
                'is_complete_file' => $chunk['is_complete_file'],
                'chunk_size_bytes' => $chunk['chunk_bytes'],
                'symbols_declared' => json_encode($chunk['symbols_declared']),
                'symbols_used' => json_encode($chunk['symbols_used']),
                'imports' => json_encode($chunk['imports']),
                'references' => json_encode($chunk['references']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in batches of 500
        foreach (array_chunk($records, 500) as $batch) {
            ProjectFileChunk::insert($batch);
        }

        // Save chunk files grouped by path (first 8 chars of path hash)
        $this->saveChunkFiles($project, $chunks);
    }

    private function saveChunkFiles(Project $project, array $chunks): void
    {
        $chunksByGroup = [];

        foreach ($chunks as $chunk) {
            // Group by first 8 chars of SHA1(path) for filesystem organization
            $pathHash = substr(sha1($chunk['file_path']), 0, 8);
            $chunksByGroup[$pathHash][] = $chunk;
        }

        foreach ($chunksByGroup as $pathHash => $pathChunks) {
            $chunkFile = $project->chunks_path . '/' . $pathHash . '.json';
            file_put_contents($chunkFile, json_encode([
                'path_hash' => $pathHash,
                'file_path' => $pathChunks[0]['file_path'],
                'file_sha1' => $pathChunks[0]['file_sha1'],
                'chunk_count' => count($pathChunks),
                'chunks' => array_map(function ($c) {
                    // Don't include full content in the grouped file
                    $stripped = $c;
                    unset($stripped['content']);
                    return $stripped;
                }, $pathChunks),
            ], JSON_PRETTY_PRINT));
        }
    }

    private function savePathIndex(Project $project, array $fileToChunks): void
    {
        $indexPath = $project->indexes_path . '/path_index.json';
        file_put_contents($indexPath, json_encode($fileToChunks, JSON_PRETTY_PRINT));
    }

    private function saveManifest(Project $project): void
    {
        $files = $project->files()->get();
        $chunks = $project->chunks()->get()->groupBy('path');

        $manifest = [
            'version' => '2.1.0',
            'project_id' => $project->id,
            'repo_full_name' => $project->repo_full_name,
            'default_branch' => $project->default_branch,
            'head_commit_sha' => $project->last_commit_sha,
            'scanned_at' => now()->toIso8601String(),
            'exclusion_rules_version' => $this->exclusionMatcher->getRulesVersion(),
            'stats' => [
                'total_files' => $project->total_files,
                'total_lines' => $project->total_lines,
                'total_bytes' => $project->total_size_bytes,
            ],
            'files' => $files->map(function ($f) use ($chunks) {
                $fileChunks = $chunks->get($f->path, collect());
                return [
                    'file_id' => 'f_' . substr(sha1($f->path), 0, 12),
                    'path' => $f->path,
                    'extension' => $f->extension,
                    'language' => $f->language,
                    'size_bytes' => $f->size_bytes,
                    'line_count' => $f->line_count,
                    'is_binary' => $f->is_binary,
                    'is_excluded' => $f->is_excluded,
                    'sha1' => $f->sha1,
                    'framework_hints' => $f->framework_hints ?? [],
                    'chunk_count' => $fileChunks->count(),
                    'chunk_ids' => $fileChunks->pluck('chunk_id')->toArray(),
                ];
            })->toArray(),
        ];

        $manifestPath = $project->knowledge_path . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    private function saveDirectories(Project $project): void
    {
        $scanner = new ScannerService();
        $directories = $scanner->getDirectorySummary($project);

        $dirPath = $project->knowledge_path . '/directories.json';
        file_put_contents($dirPath, json_encode($directories, JSON_PRETTY_PRINT));
    }

    private function clearChunkFiles(Project $project): void
    {
        $chunksPath = $project->chunks_path;
        if (is_dir($chunksPath)) {
            $files = glob($chunksPath . '/*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    private function getDirectoryPriorityOrder(): string
    {
        $cases = [];
        foreach ($this->priorityDirs as $index => $dir) {
            $escapedDir = addslashes($dir);
            $cases[] = "WHEN path LIKE '{$escapedDir}%' THEN {$index}";
        }
        $caseStatement = implode(' ', $cases);
        return "CASE {$caseStatement} ELSE 999 END, path";
    }

    public function rebuildForFiles(Project $project, array $filePaths): void
    {
        $this->exclusionMatcher = new ExclusionMatcher($project);

        // Delete existing chunks for these files
        $project->chunks()->whereIn('path', $filePaths)->delete();

        $fileToChunks = $this->loadPathIndex($project);

        // Remove old entries
        foreach ($filePaths as $path) {
            unset($fileToChunks[$path]);
        }

        // Rebuild chunks for specified files
        $files = $project->files()
            ->whereIn('path', $filePaths)
            ->where('is_binary', false)
            ->where('is_excluded', false)
            ->get();

        $allChunks = [];

        foreach ($files as $file) {
            $fullPath = $project->repo_path . '/' . $file->path;

            if (!file_exists($fullPath)) {
                continue;
            }

            $content = @file_get_contents($fullPath);
            if ($content === false) {
                continue;
            }

            // Update file SHA1
            $newSha1 = sha1($content);
            $file->update(['sha1' => $newSha1]);

            $chunks = $this->chunkFile($project, $file, $content);

            foreach ($chunks as $chunk) {
                $allChunks[] = $chunk;
                $fileToChunks[$file->path][] = $chunk['chunk_id'];
            }
        }

        if (!empty($allChunks)) {
            $this->saveChunks($project, $allChunks);
        }

        $this->savePathIndex($project, $fileToChunks);
    }

    private function loadPathIndex(Project $project): array
    {
        $indexPath = $project->indexes_path . '/path_index.json';
        if (file_exists($indexPath)) {
            return json_decode(file_get_contents($indexPath), true) ?? [];
        }
        return [];
    }
}
