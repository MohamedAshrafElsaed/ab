<?php

namespace App\Services\Projects\Concerns;

/**
 * Provides deterministic chunk ID generation.
 *
 * Use this trait to ensure consistent chunk ID generation across all services.
 * The chunk ID format is: sha1(file_path + ":" + file_sha1 + ":" + start_line + "-" + end_line), truncated to 16 chars.
 */
trait HasDeterministicChunkId
{
    /**
     * Generate a deterministic chunk ID.
     *
     * @param string $filePath The relative file path
     * @param string $fileSha1 The SHA1 hash of the file content
     * @param int $startLine The starting line number (1-indexed, inclusive)
     * @param int $endLine The ending line number (1-indexed, inclusive)
     * @return string A 16-character hexadecimal chunk ID
     */
    public static function generateChunkId(string $filePath, string $fileSha1, int $startLine, int $endLine): string
    {
        $input = "{$filePath}:{$fileSha1}:{$startLine}-{$endLine}";
        return substr(sha1($input), 0, 16);
    }

    /**
     * Verify a chunk ID matches the expected value for the given parameters.
     *
     * @param string $chunkId The chunk ID to verify
     * @param string $filePath The relative file path
     * @param string $fileSha1 The SHA1 hash of the file content
     * @param int $startLine The starting line number
     * @param int $endLine The ending line number
     * @return bool True if the chunk ID is valid
     */
    public static function verifyChunkId(string $chunkId, string $filePath, string $fileSha1, int $startLine, int $endLine): bool
    {
        $expected = static::generateChunkId($filePath, $fileSha1, $startLine, $endLine);
        return $chunkId === $expected;
    }

    /**
     * Check if a string is a valid new-format chunk ID (16 hex chars).
     *
     * @param string $chunkId The chunk ID to check
     * @return bool True if it's a valid new-format chunk ID
     */
    public static function isValidChunkIdFormat(string $chunkId): bool
    {
        return (bool) preg_match('/^[a-f0-9]{16}$/', $chunkId);
    }

    /**
     * Check if a chunk ID is in a legacy format.
     *
     * Legacy formats include:
     * - v1: chunk_XXXX (e.g., chunk_0001)
     * - v2: path_hash:start-end (e.g., abc123def456:1-100)
     *
     * @param string $chunkId The chunk ID to check
     * @return bool True if it's a legacy format
     */
    public static function isLegacyChunkId(string $chunkId): bool
    {
        // v1 format: chunk_XXXX
        if (preg_match('/^chunk_\d{4}$/', $chunkId)) {
            return true;
        }

        // v2 format: path_hash:start-end
        if (preg_match('/^[a-f0-9]{12}:\d+-\d+$/', $chunkId)) {
            return true;
        }

        return false;
    }
}
