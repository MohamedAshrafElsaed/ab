<?php

namespace App\Services\Files;

use App\DTOs\WriteResult;
use App\Models\Project;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service for safe file operations with backup support.
 */
class FileWriterService
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
    }

    /**
     * Create a new file with content.
     */
    public function createFile(Project $project, string $relativePath, string $content): WriteResult
    {
        $fullPath = $this->resolvePath($project, $relativePath);

        try {
            if (file_exists($fullPath)) {
                return WriteResult::failure($relativePath, 'File already exists');
            }

            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    return WriteResult::failure($relativePath, "Failed to create directory: {$directory}");
                }
            }

            if (file_put_contents($fullPath, $content) === false) {
                return WriteResult::failure($relativePath, 'Failed to write file content');
            }

            Log::info('FileWriter: Created file', [
                'project_id' => $project->id,
                'path' => $relativePath,
                'size' => strlen($content),
            ]);

            return WriteResult::success($relativePath, null, null, [
                'size_bytes' => strlen($content),
                'created_at' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            Log::error('FileWriter: Failed to create file', [
                'project_id' => $project->id,
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
            return WriteResult::failure($relativePath, $e->getMessage());
        }
    }

    /**
     * Modify an existing file.
     */
    public function modifyFile(Project $project, string $relativePath, string $newContent): WriteResult
    {
        $fullPath = $this->resolvePath($project, $relativePath);

        try {
            if (!file_exists($fullPath)) {
                return WriteResult::failure($relativePath, 'File does not exist');
            }

            $originalContent = file_get_contents($fullPath);
            if ($originalContent === false) {
                return WriteResult::failure($relativePath, 'Failed to read original file');
            }

            $backupPath = $this->backup($project, $relativePath, $originalContent);

            if (file_put_contents($fullPath, $newContent) === false) {
                $this->restoreFromBackup($backupPath, $fullPath);
                return WriteResult::failure($relativePath, 'Failed to write modified content');
            }

            Log::info('FileWriter: Modified file', [
                'project_id' => $project->id,
                'path' => $relativePath,
                'original_size' => strlen($originalContent),
                'new_size' => strlen($newContent),
                'backup' => $backupPath,
            ]);

            return WriteResult::success($relativePath, $backupPath, $originalContent, [
                'original_size' => strlen($originalContent),
                'new_size' => strlen($newContent),
                'modified_at' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            Log::error('FileWriter: Failed to modify file', [
                'project_id' => $project->id,
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
            return WriteResult::failure($relativePath, $e->getMessage());
        }
    }

    /**
     * Delete a file (with backup).
     */
    public function deleteFile(Project $project, string $relativePath): WriteResult
    {
        $fullPath = $this->resolvePath($project, $relativePath);

        try {
            if (!file_exists($fullPath)) {
                return WriteResult::failure($relativePath, 'File does not exist');
            }

            $originalContent = file_get_contents($fullPath);
            if ($originalContent === false) {
                return WriteResult::failure($relativePath, 'Failed to read file before deletion');
            }

            $backupPath = $this->backup($project, $relativePath, $originalContent);

            if (!unlink($fullPath)) {
                return WriteResult::failure($relativePath, 'Failed to delete file');
            }

            Log::info('FileWriter: Deleted file', [
                'project_id' => $project->id,
                'path' => $relativePath,
                'backup' => $backupPath,
            ]);

            return WriteResult::success($relativePath, $backupPath, $originalContent, [
                'deleted_at' => now()->toIso8601String(),
                'size_bytes' => strlen($originalContent),
            ]);
        } catch (Exception $e) {
            Log::error('FileWriter: Failed to delete file', [
                'project_id' => $project->id,
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
            return WriteResult::failure($relativePath, $e->getMessage());
        }
    }

    /**
     * Rename/move a file.
     */
    public function moveFile(Project $project, string $oldPath, string $newPath): WriteResult
    {
        $oldFullPath = $this->resolvePath($project, $oldPath);
        $newFullPath = $this->resolvePath($project, $newPath);

        try {
            if (!file_exists($oldFullPath)) {
                return WriteResult::failure($oldPath, 'Source file does not exist');
            }

            if (file_exists($newFullPath)) {
                return WriteResult::failure($newPath, 'Destination file already exists');
            }

            $newDirectory = dirname($newFullPath);
            if (!is_dir($newDirectory)) {
                if (!mkdir($newDirectory, 0755, true)) {
                    return WriteResult::failure($newPath, "Failed to create directory: {$newDirectory}");
                }
            }

            $originalContent = file_get_contents($oldFullPath);
            $backupPath = $this->backup($project, $oldPath, $originalContent ?: '');

            if (!rename($oldFullPath, $newFullPath)) {
                return WriteResult::failure($oldPath, 'Failed to move file');
            }

            Log::info('FileWriter: Moved file', [
                'project_id' => $project->id,
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'backup' => $backupPath,
            ]);

            return WriteResult::success($newPath, $backupPath, $originalContent, [
                'old_path' => $oldPath,
                'moved_at' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            Log::error('FileWriter: Failed to move file', [
                'project_id' => $project->id,
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'error' => $e->getMessage(),
            ]);
            return WriteResult::failure($oldPath, $e->getMessage());
        }
    }

    /**
     * Read current file content.
     */
    public function readFile(Project $project, string $relativePath): ?string
    {
        $fullPath = $this->resolvePath($project, $relativePath);

        if (!file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        return $content === false ? null : $content;
    }

    /**
     * Check if file exists.
     */
    public function fileExists(Project $project, string $relativePath): bool
    {
        return file_exists($this->resolvePath($project, $relativePath));
    }

    /**
     * Create backup of a file.
     */
    public function backup(Project $project, string $relativePath, string $content): string
    {
        $backupDir = $this->getBackupDirectory($project);

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $safeFileName = str_replace(['/', '\\'], '_', $relativePath);
        $backupPath = "{$backupDir}/{$timestamp}_{$safeFileName}";

        file_put_contents($backupPath, $content);

        return $backupPath;
    }

    /**
     * Restore from backup.
     */
    public function restoreFromBackup(string $backupPath, string $targetPath): bool
    {
        if (!file_exists($backupPath)) {
            Log::warning('FileWriter: Backup file not found', ['backup' => $backupPath]);
            return false;
        }

        $content = file_get_contents($backupPath);
        if ($content === false) {
            return false;
        }

        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $result = file_put_contents($targetPath, $content);

        if ($result !== false) {
            Log::info('FileWriter: Restored from backup', [
                'backup' => $backupPath,
                'target' => $targetPath,
            ]);
        }

        return $result !== false;
    }

    /**
     * Restore original content to a file.
     */
    public function restoreContent(Project $project, string $relativePath, string $content): WriteResult
    {
        $fullPath = $this->resolvePath($project, $relativePath);

        try {
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            if (file_put_contents($fullPath, $content) === false) {
                return WriteResult::failure($relativePath, 'Failed to restore content');
            }

            Log::info('FileWriter: Restored content', [
                'project_id' => $project->id,
                'path' => $relativePath,
            ]);

            return WriteResult::success($relativePath, null, null, [
                'restored_at' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            return WriteResult::failure($relativePath, $e->getMessage());
        }
    }

    /**
     * Delete file permanently (for created files during rollback).
     */
    public function deleteFilePermanently(Project $project, string $relativePath): WriteResult
    {
        $fullPath = $this->resolvePath($project, $relativePath);

        try {
            if (!file_exists($fullPath)) {
                return WriteResult::success($relativePath, null, null, [
                    'note' => 'File did not exist',
                ]);
            }

            if (!unlink($fullPath)) {
                return WriteResult::failure($relativePath, 'Failed to delete file');
            }

            return WriteResult::success($relativePath);
        } catch (Exception $e) {
            return WriteResult::failure($relativePath, $e->getMessage());
        }
    }

    /**
     * Clean up old backups.
     */
    public function cleanupOldBackups(Project $project, int $keepDays = 7): int
    {
        $backupDir = $this->getBackupDirectory($project);

        if (!is_dir($backupDir)) {
            return 0;
        }

        $cutoff = now()->subDays($keepDays)->timestamp;
        $deleted = 0;

        foreach (scandir($backupDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = "{$backupDir}/{$file}";
            if (filemtime($filePath) < $cutoff) {
                unlink($filePath);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Resolve full path from project and relative path.
     */
    private function resolvePath(Project $project, string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        return rtrim($project->repo_path, '/') . '/' . $relativePath;
    }

    /**
     * Get backup directory for project.
     */
    private function getBackupDirectory(Project $project): string
    {
        return "{$this->backupDir}/{$project->id}";
    }
}
