<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectFile;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ScannerService
{
    private ExclusionMatcher $exclusionMatcher;
    private LanguageDetector $languageDetector;
    private FrameworkHintDetector $frameworkDetector;
    private SymbolExtractor $symbolExtractor;
    private int $maxFileSize;

    private array $exclusionLog = [];
    private int $excludedCount = 0;

    public function __construct()
    {
        $this->languageDetector = new LanguageDetector();
        $this->frameworkDetector = new FrameworkHintDetector();
        $this->symbolExtractor = new SymbolExtractor();
        $this->maxFileSize = config('projects.max_file_size', 1024 * 1024);
    }

    public function scanDirectory(Project $project, ?callable $progressCallback = null): array
    {
        // Initialize exclusion matcher with project context
        $this->exclusionMatcher = new ExclusionMatcher($project);
        $this->exclusionLog = [];
        $this->excludedCount = 0;

        $repoPath = realpath($project->repo_path);

        if (!$repoPath || !is_dir($repoPath)) {
            throw new \Exception("Repository path does not exist: {$project->repo_path}");
        }

        $files = [];
        $totalFiles = 0;
        $totalLines = 0;
        $totalBytes = 0;
        $binaryCount = 0;

        // Collect all files first
        $allFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $repoPath,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $allFiles[] = $file;
            }
        }

        $totalCount = count($allFiles);
        $processed = 0;

        foreach ($allFiles as $file) {
            $relativePath = $this->getRelativePath($repoPath, $file->getPathname());

            // Check exclusion rules
            $exclusionResult = $this->exclusionMatcher->shouldExclude($relativePath);

            if ($exclusionResult !== false) {
                // Log exclusion (limit log size)
                if (count($this->exclusionLog) < 1000) {
                    $this->exclusionLog[] = [
                        'path' => $relativePath,
                        'rule' => $exclusionResult['rule'],
                        'matched_at' => $exclusionResult['matched_at'],
                    ];
                }
                $this->excludedCount++;
                $processed++;
                continue;
            }

            // Check if binary
            $isBinary = $this->exclusionMatcher->isBinary($relativePath);
            if ($isBinary) {
                $binaryCount++;
            }

            $fileData = $this->scanFile($file, $relativePath, $project);
            if ($fileData) {
                $files[] = $fileData;
                $totalFiles++;
                $totalLines += $fileData['line_count'];
                $totalBytes += $fileData['size_bytes'];
            }

            $processed++;

            if ($progressCallback && $processed % 100 === 0) {
                $progressCallback($processed, $totalCount);
            }
        }

        return [
            'files' => $files,
            'stats' => [
                'total_files' => $totalFiles,
                'total_lines' => $totalLines,
                'total_bytes' => $totalBytes,
                'excluded_count' => $this->excludedCount,
                'binary_count' => $binaryCount,
            ],
            'exclusion_log' => $this->exclusionLog,
            'exclusion_rules_version' => $this->exclusionMatcher->getRulesVersion(),
        ];
    }

    public function scanFile(SplFileInfo $file, string $relativePath, Project $project): ?array
    {
        $size = $file->getSize();
        $isBinary = $this->exclusionMatcher->isBinary($relativePath);

        // Generate stable file_id
        $fileId = 'f_' . substr(sha1($relativePath), 0, 12);

        // Detect extension (handle compound extensions)
        $extension = $this->getExtension($relativePath);

        // Detect language
        $language = $this->languageDetector->detect($relativePath);

        $lineCount = 0;
        $sha1 = null;
        $mimeType = null;
        $content = null;
        $frameworkHints = [];
        $symbolsDeclared = [];
        $imports = [];

        if (!$isBinary && $size <= $this->maxFileSize && $size > 0) {
            $content = @file_get_contents($file->getPathname());
            if ($content !== false) {
                $sha1 = sha1($content);
                $lineCount = substr_count($content, "\n") + 1;

                // Detect framework hints
                $frameworkHints = $this->frameworkDetector->detect($relativePath, $content);

                // Extract symbols and imports
                $symbolsDeclared = $this->symbolExtractor->extractDeclarations($content, $extension);
                $imports = $this->symbolExtractor->extractImports($content, $extension);

                // Update language detection with content
                $language = $this->languageDetector->detect($relativePath, $content);
            }
        } elseif ($size > 0) {
            $sha1 = @sha1_file($file->getPathname());
        }

        // Get mime type
        if (function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($file->getPathname());
        }

        return [
            'file_id' => $fileId,
            'path' => $relativePath,
            'extension' => $extension ?: null,
            'language' => $language,
            'size_bytes' => $size,
            'sha1' => $sha1,
            'line_count' => $lineCount,
            'is_binary' => $isBinary,
            'is_excluded' => false,
            'exclusion_reason' => null,
            'mime_type' => $mimeType,
            'framework_hints' => $frameworkHints,
            'symbols_declared' => $symbolsDeclared,
            'imports' => $imports,
            'file_modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
        ];
    }

    public function persistManifest(Project $project, array $files): void
    {
        // Delete existing files
        $project->files()->delete();

        // Batch insert in chunks for performance
        $chunks = array_chunk($files, 500);

        foreach ($chunks as $chunk) {
            $records = array_map(function ($file) use ($project) {
                return [
                    'project_id' => $project->id,
                    'file_id' => $file['file_id'],
                    'path' => $file['path'],
                    'extension' => $file['extension'],
                    'language' => $file['language'],
                    'size_bytes' => $file['size_bytes'],
                    'sha1' => $file['sha1'],
                    'line_count' => $file['line_count'],
                    'is_binary' => $file['is_binary'],
                    'is_excluded' => $file['is_excluded'],
                    'exclusion_reason' => $file['exclusion_reason'],
                    'mime_type' => $file['mime_type'] ?? null,
                    'framework_hints' => json_encode($file['framework_hints'] ?? []),
                    'symbols_declared' => json_encode($file['symbols_declared'] ?? []),
                    'imports' => json_encode($file['imports'] ?? []),
                    'file_modified_at' => $file['file_modified_at'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $chunk);

            ProjectFile::insert($records);
        }
    }

    public function updateChangedFiles(Project $project, array $changes): array
    {
        $this->exclusionMatcher = new ExclusionMatcher($project);
        $repoPath = $project->repo_path;
        $updatedFiles = [];

        // Delete removed files
        if (!empty($changes['deleted'])) {
            $project->files()->whereIn('path', $changes['deleted'])->delete();
            $project->chunks()->whereIn('path', $changes['deleted'])->delete();
        }

        // Process added and modified files
        $toProcess = array_merge($changes['added'], $changes['modified']);

        foreach ($toProcess as $relativePath) {
            // Check if should be excluded
            $exclusionResult = $this->exclusionMatcher->shouldExclude($relativePath);
            if ($exclusionResult !== false) {
                // Mark as excluded or skip
                $project->files()->updateOrCreate(
                    ['path' => $relativePath],
                    [
                        'is_excluded' => true,
                        'exclusion_reason' => $exclusionResult['rule'],
                    ]
                );
                continue;
            }

            $fullPath = $repoPath . '/' . $relativePath;

            if (!file_exists($fullPath)) {
                continue;
            }

            $file = new SplFileInfo($fullPath);
            $fileData = $this->scanFile($file, $relativePath, $project);

            if ($fileData) {
                $project->files()->updateOrCreate(
                    ['path' => $relativePath],
                    [
                        'file_id' => $fileData['file_id'],
                        'extension' => $fileData['extension'],
                        'language' => $fileData['language'],
                        'size_bytes' => $fileData['size_bytes'],
                        'sha1' => $fileData['sha1'],
                        'line_count' => $fileData['line_count'],
                        'is_binary' => $fileData['is_binary'],
                        'is_excluded' => false,
                        'exclusion_reason' => null,
                        'mime_type' => $fileData['mime_type'] ?? null,
                        'framework_hints' => json_encode($fileData['framework_hints'] ?? []),
                        'symbols_declared' => json_encode($fileData['symbols_declared'] ?? []),
                        'imports' => json_encode($fileData['imports'] ?? []),
                        'file_modified_at' => $fileData['file_modified_at'],
                    ]
                );
                $updatedFiles[] = $relativePath;
            }
        }

        return $updatedFiles;
    }

    public function buildDirectoryTree(Project $project): array
    {
        $files = $project->files()->where('is_excluded', false)->get();
        $tree = [];

        foreach ($files as $file) {
            $parts = explode('/', $file->path);
            $current = &$tree;

            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $current['_files'][] = [
                        'name' => $part,
                        'path' => $file->path,
                        'size' => $file->size_bytes,
                        'lines' => $file->line_count,
                        'language' => $file->language,
                    ];
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = ['_files' => []];
                    }
                    $current = &$current[$part];
                }
            }
        }

        return $tree;
    }

    public function getDirectorySummary(Project $project): array
    {
        $files = $project->files()->where('is_excluded', false)->get();

        $directories = [];

        foreach ($files as $file) {
            $path = $file->path;
            $dir = dirname($path);

            if ($dir === '.') {
                $dir = '(root)';
            }

            if (!isset($directories[$dir])) {
                $directories[$dir] = [
                    'directory' => $dir,
                    'file_count' => 0,
                    'total_size' => 0,
                    'total_lines' => 0,
                    'depth' => $dir === '(root)' ? 0 : substr_count($dir, '/') + 1,
                    'languages' => [],
                ];
            }

            $directories[$dir]['file_count']++;
            $directories[$dir]['total_size'] += $file->size_bytes;
            $directories[$dir]['total_lines'] += $file->line_count;

            // Track languages
            $lang = $file->language ?? 'unknown';
            if (!isset($directories[$dir]['languages'][$lang])) {
                $directories[$dir]['languages'][$lang] = 0;
            }
            $directories[$dir]['languages'][$lang]++;
        }

        // Sort by path for hierarchical display
        ksort($directories);

        return array_values($directories);
    }

    public function getTopLevelDirectorySummary(Project $project): array
    {
        $files = $project->files()->where('is_excluded', false)->get();

        $directories = [];

        foreach ($files as $file) {
            $path = $file->path;

            if (str_contains($path, '/')) {
                $parts = explode('/', $path);
                $topDir = $parts[0];
            } else {
                $topDir = '(root)';
            }

            if (!isset($directories[$topDir])) {
                $directories[$topDir] = [
                    'directory' => $topDir,
                    'file_count' => 0,
                    'total_size' => 0,
                    'total_lines' => 0,
                ];
            }

            $directories[$topDir]['file_count']++;
            $directories[$topDir]['total_size'] += $file->size_bytes;
            $directories[$topDir]['total_lines'] += $file->line_count;
        }

        usort($directories, fn($a, $b) => $b['file_count'] <=> $a['file_count']);

        return array_values($directories);
    }

    private function getRelativePath(string $basePath, string $fullPath): string
    {
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $fullPath = str_replace('\\', '/', $fullPath);

        if (str_starts_with($fullPath, $basePath . '/')) {
            return substr($fullPath, strlen($basePath) + 1);
        }

        $realBase = realpath($basePath);
        $realFull = realpath($fullPath);

        if ($realBase && $realFull && str_starts_with($realFull, $realBase . DIRECTORY_SEPARATOR)) {
            $relative = substr($realFull, strlen($realBase) + 1);
            return str_replace('\\', '/', $relative);
        }

        return basename($fullPath);
    }

    private function getExtension(string $path): ?string
    {
        // Handle compound extensions
        if (preg_match('/\.blade\.php$/i', $path)) {
            return 'blade.php';
        }
        if (preg_match('/\.min\.js$/i', $path)) {
            return 'min.js';
        }
        if (preg_match('/\.min\.css$/i', $path)) {
            return 'min.css';
        }
        if (preg_match('/\.d\.ts$/i', $path)) {
            return 'd.ts';
        }
        if (preg_match('/\.spec\.ts$/i', $path)) {
            return 'spec.ts';
        }
        if (preg_match('/\.spec\.js$/i', $path)) {
            return 'spec.js';
        }
        if (preg_match('/\.test\.ts$/i', $path)) {
            return 'test.ts';
        }
        if (preg_match('/\.test\.js$/i', $path)) {
            return 'test.js';
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return $extension ?: null;
    }

    public function getExclusionLog(): array
    {
        return $this->exclusionLog;
    }

    public function getExcludedCount(): int
    {
        return $this->excludedCount;
    }
}
