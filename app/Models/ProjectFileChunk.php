<?php

namespace App\Models;

use App\Services\Projects\Concerns\HasDeterministicChunkId;
use Database\Factories\ProjectFileChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $project_id
 * @property string $chunk_id
 * @property string|null $old_chunk_id
 * @property string $path
 * @property int $start_line
 * @property int|null $end_line
 * @property int $chunk_index
 * @property string|null $sha1
 * @property string|null $chunk_sha1
 * @property bool $is_complete_file
 * @property array<array-key, mixed>|null $symbols_declared
 * @property array<array-key, mixed>|null $symbols_used
 * @property array<array-key, mixed>|null $imports
 * @property array<array-key, mixed>|null $references
 * @property string|null $chunk_file_path
 * @property int $chunk_size_bytes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ProjectFile|null $file
 * @property-read int $line_count
 * @property-read string $path_hash
 * @property-read \App\Models\Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk byChunkId(string $chunkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk byOldChunkId(string $oldChunkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk completeFiles()
 * @method static \Database\Factories\ProjectFileChunkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk forFile(string $path)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk partialFiles()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereChunkFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereChunkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereChunkIndex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereChunkSha1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereChunkSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereEndLine($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereImports($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereIsCompleteFile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereOldChunkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereReferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereSha1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereStartLine($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereSymbolsDeclared($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereSymbolsUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFileChunk whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ProjectFileChunk extends Model
{
    /** @use HasFactory<ProjectFileChunkFactory> */
    use HasFactory;
    use HasDeterministicChunkId;

    protected $fillable = [
        'project_id',
        'chunk_id',
        'old_chunk_id',
        'path',
        'start_line',
        'end_line',
        'chunk_index',
        'sha1',
        'chunk_sha1',
        'is_complete_file',
        'chunk_file_path',
        'chunk_size_bytes',
        'symbols_declared',
        'symbols_used',
        'imports',
        'references',
    ];

    protected function casts(): array
    {
        return [
            'project_id' => 'string',
            'is_complete_file' => 'boolean',
            'symbols_declared' => 'array',
            'symbols_used' => 'array',
            'imports' => 'array',
            'references' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class, 'path', 'path')
            ->where('project_id', $this->project_id);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getLineCountAttribute(): int
    {
        return $this->end_line - $this->start_line + 1;
    }

    public function getPathHashAttribute(): string
    {
        return substr(sha1($this->path), 0, 12);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForFile($query, string $path)
    {
        return $query->where('path', $path)->orderBy('start_line');
    }

    public function scopeCompleteFiles($query)
    {
        return $query->where('is_complete_file', true);
    }

    public function scopePartialFiles($query)
    {
        return $query->where('is_complete_file', false);
    }

    public function scopeByChunkId($query, string $chunkId)
    {
        return $query->where('chunk_id', $chunkId);
    }

    public function scopeByOldChunkId($query, string $oldChunkId)
    {
        return $query->where('old_chunk_id', $oldChunkId);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    public function getContent(Project $project): ?string
    {
        $fullPath = $project->repo_path . '/' . $this->path;

        if (!file_exists($fullPath)) {
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $lines = explode("\n", $content);

        $startLine = max(1, $this->start_line);
        $endLine = min(count($lines), $this->end_line);

        if ($startLine > count($lines)) {
            return null;
        }

        $chunkLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return implode("\n", $chunkLines);
    }

    public function verifySha1(Project $project): bool
    {
        $content = $this->getContent($project);

        if ($content === null) {
            return false;
        }

        return sha1($content) === $this->chunk_sha1;
    }

    public function verifyChunkId(): bool
    {
        $expectedId = self::generateChunkId($this->path, $this->sha1, $this->start_line, $this->end_line);
        return $this->chunk_id === $expectedId;
    }

    public static function parseChunkId(string $chunkId): ?array
    {
        if (preg_match('/^[a-f0-9]{16}$/', $chunkId)) {
            return [
                'format' => 'new',
                'hash' => $chunkId,
            ];
        }

        if (preg_match('/^([a-f0-9]{12}):(\d+)-(\d+)$/', $chunkId, $matches)) {
            return [
                'format' => 'legacy_v2',
                'path_hash' => $matches[1],
                'start_line' => (int)$matches[2],
                'end_line' => (int)$matches[3],
            ];
        }

        if (preg_match('/^chunk_(\d{4})$/', $chunkId, $matches)) {
            return [
                'format' => 'legacy_v1',
                'index' => (int)$matches[1],
            ];
        }

        return null;
    }

    public static function isOldFormat(string $chunkId): bool
    {
        return (bool)preg_match('/^chunk_\d{4}$/', $chunkId);
    }

    public static function isLegacyV2Format(string $chunkId): bool
    {
        return (bool)preg_match('/^[a-f0-9]{12}:\d+-\d+$/', $chunkId);
    }

    public static function isNewFormat(string $chunkId): bool
    {
        return self::isValidChunkIdFormat($chunkId);
    }

    public function regenerateChunkId(): string
    {
        $newId = self::generateChunkId($this->path, $this->sha1, $this->start_line, $this->end_line);
        $this->old_chunk_id = $this->chunk_id;
        $this->chunk_id = $newId;
        return $newId;
    }

    public function isChunkIdValid(): bool
    {
        return self::verifyChunkId();
    }
}
