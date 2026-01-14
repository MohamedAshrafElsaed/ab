<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Services\Projects\Concerns\HasDeterministicChunkId;
use Exception;
use Generator;

/**
 * Reads knowledge base output for retrieval operations.
 *
 * Provides efficient access to scan metadata, file index, and chunks
 * from the standardized output format.
 */
class KnowledgeBaseReader
{
    use HasDeterministicChunkId;
    private Project $project;
    private string $scanId;
    private string $basePath;
    private ?array $scanMetaCache = null;
    private ?array $filesIndexCache = null;
    private array $chunkCache = [];

    public function __construct(Project $project, ?string $scanId = null)
    {
        $this->project = $project;
        $this->scanId = $scanId ?? $project->last_kb_scan_id;

        if (!$this->scanId) {
            throw new Exception("No knowledge base scan available for project {$project->id}");
        }

        $this->basePath = $project->getKbScanPath($this->scanId);

        if (!is_dir($this->basePath)) {
            throw new Exception("Knowledge base not found: {$this->basePath}");
        }
    }

    /**
     * Get the scan metadata.
     */
    public function getScanMeta(): array
    {
        if ($this->scanMetaCache === null) {
            $path = $this->basePath . '/scan_meta.json';
            if (!file_exists($path)) {
                throw new Exception("scan_meta.json not found in {$this->basePath}");
            }
            $this->scanMetaCache = json_decode(file_get_contents($path), true);
        }
        return $this->scanMetaCache;
    }

    /**
     * Get the files index as an array.
     * For large repositories, consider using getFilesIndexIterator() instead.
     */
    public function getFilesIndex(): array
    {
        if ($this->filesIndexCache === null) {
            $jsonPath = $this->basePath . '/files_index.json';
            $ndjsonPath = $this->basePath . '/files_index.ndjson';

            if (file_exists($ndjsonPath)) {
                $this->filesIndexCache = [];
                foreach ($this->readNdjson($ndjsonPath) as $record) {
                    $this->filesIndexCache[] = $record;
                }
            } elseif (file_exists($jsonPath)) {
                $this->filesIndexCache = json_decode(file_get_contents($jsonPath), true);
            } else {
                throw new Exception("files_index not found in {$this->basePath}");
            }
        }
        return $this->filesIndexCache;
    }

    /**
     * Get an iterator over the files index (memory-efficient for large repos).
     */
    public function getFilesIndexIterator(): Generator
    {
        $jsonPath = $this->basePath . '/files_index.json';
        $ndjsonPath = $this->basePath . '/files_index.ndjson';

        if (file_exists($ndjsonPath)) {
            yield from $this->readNdjson($ndjsonPath);
        } elseif (file_exists($jsonPath)) {
            $data = json_decode(file_get_contents($jsonPath), true);
            foreach ($data as $record) {
                yield $record;
            }
        } else {
            throw new Exception("files_index not found in {$this->basePath}");
        }
    }

    /**
     * Get file info by path.
     */
    public function getFileInfo(string $filePath): ?array
    {
        foreach ($this->getFilesIndexIterator() as $file) {
            if ($file['file_path'] === $filePath) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Get all chunk IDs for a file.
     */
    public function getChunkIdsForFile(string $filePath): array
    {
        $file = $this->getFileInfo($filePath);
        return $file['chunk_ids'] ?? [];
    }

    /**
     * Get an iterator over all chunks (memory-efficient).
     */
    public function getChunksIterator(): Generator
    {
        $path = $this->basePath . '/chunks.ndjson';
        if (!file_exists($path)) {
            throw new Exception("chunks.ndjson not found in {$this->basePath}");
        }
        yield from $this->readNdjson($path);
    }

    /**
     * Get a specific chunk by ID.
     */
    public function getChunk(string $chunkId): ?array
    {
        // Check cache first
        if (isset($this->chunkCache[$chunkId])) {
            return $this->chunkCache[$chunkId];
        }

        // Search through chunks
        foreach ($this->getChunksIterator() as $chunk) {
            if ($chunk['chunk_id'] === $chunkId) {
                // Cache it
                $this->chunkCache[$chunkId] = $chunk;
                return $chunk;
            }
        }

        return null;
    }

    /**
     * Get multiple chunks by IDs (optimized batch retrieval).
     */
    public function getChunks(array $chunkIds): array
    {
        $results = [];
        $needed = array_flip($chunkIds);

        // First, check cache
        foreach ($chunkIds as $id) {
            if (isset($this->chunkCache[$id])) {
                $results[$id] = $this->chunkCache[$id];
                unset($needed[$id]);
            }
        }

        // If all found in cache, return early
        if (empty($needed)) {
            return $results;
        }

        // Scan chunks file for remaining
        foreach ($this->getChunksIterator() as $chunk) {
            if (isset($needed[$chunk['chunk_id']])) {
                $results[$chunk['chunk_id']] = $chunk;
                $this->chunkCache[$chunk['chunk_id']] = $chunk;
                unset($needed[$chunk['chunk_id']]);

                if (empty($needed)) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Get all chunks for a specific file.
     */
    public function getChunksForFile(string $filePath): array
    {
        $chunkIds = $this->getChunkIdsForFile($filePath);
        if (empty($chunkIds)) {
            return [];
        }

        $chunks = $this->getChunks($chunkIds);

        // Sort by start_line
        uasort($chunks, fn($a, $b) => $a['start_line'] <=> $b['start_line']);

        return array_values($chunks);
    }

    /**
     * Get directory statistics.
     */
    public function getDirectoryStats(): array
    {
        $path = $this->basePath . '/directory_stats.json';
        if (!file_exists($path)) {
            return ['by_directory' => [], 'by_extension' => []];
        }
        return json_decode(file_get_contents($path), true);
    }

    /**
     * Search for files by path pattern.
     */
    public function searchFiles(string $pattern): array
    {
        $results = [];
        $regex = '/' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '/i';

        foreach ($this->getFilesIndexIterator() as $file) {
            if (preg_match($regex, $file['file_path'])) {
                $results[] = $file;
            }
        }

        return $results;
    }

    /**
     * Get files by language.
     */
    public function getFilesByLanguage(string $language): array
    {
        $results = [];

        foreach ($this->getFilesIndexIterator() as $file) {
            if (($file['language'] ?? 'plaintext') === $language) {
                $results[] = $file;
            }
        }

        return $results;
    }

    /**
     * Get files by extension.
     */
    public function getFilesByExtension(string $extension): array
    {
        $results = [];

        foreach ($this->getFilesIndexIterator() as $file) {
            if (($file['extension'] ?? '') === $extension) {
                $results[] = $file;
            }
        }

        return $results;
    }

    /**
     * Validate the knowledge base consistency.
     */
    public function validate(): array
    {
        $meta = $this->getScanMeta();
        $filesCount = 0;
        $chunkIdsFromFiles = [];

        foreach ($this->getFilesIndexIterator() as $file) {
            $filesCount++;
            foreach ($file['chunk_ids'] ?? [] as $id) {
                $chunkIdsFromFiles[$id] = true;
            }
        }

        $chunksCount = 0;
        $chunkIdsFromChunks = [];

        foreach ($this->getChunksIterator() as $chunk) {
            $chunksCount++;
            $chunkIdsFromChunks[$chunk['chunk_id']] = true;
        }

        $missingInChunks = array_diff_key($chunkIdsFromFiles, $chunkIdsFromChunks);
        $orphanedChunks = array_diff_key($chunkIdsFromChunks, $chunkIdsFromFiles);

        return [
            'is_valid' => empty($missingInChunks) && empty($orphanedChunks),
            'scan_id' => $this->scanId,
            'files_count' => $filesCount,
            'chunks_count' => $chunksCount,
            'chunk_ids_in_files' => count($chunkIdsFromFiles),
            'chunk_ids_in_chunks' => count($chunkIdsFromChunks),
            'missing_in_chunks' => count($missingInChunks),
            'orphaned_chunks' => count($orphanedChunks),
            'meta_stats' => $meta['stats'] ?? [],
        ];
    }

    /**
     * Read NDJSON file as a generator.
     */
    private function readNdjson(string $path): Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new Exception("Cannot open file: {$path}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                $record = json_decode($line, true);
                if ($record !== null) {
                    yield $record;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get the base path of this knowledge base.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the scan ID.
     */
    public function getScanId(): string
    {
        return $this->scanId;
    }

    /**
     * Clear internal caches.
     */
    public function clearCache(): void
    {
        $this->scanMetaCache = null;
        $this->filesIndexCache = null;
        $this->chunkCache = [];
    }
}
