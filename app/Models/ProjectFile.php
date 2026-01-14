<?php

namespace App\Models;

use Database\Factories\ProjectFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $file_id
 * @property string $project_id
 * @property string $path
 * @property string|null $extension
 * @property string|null $language
 * @property int $size_bytes
 * @property string|null $sha1
 * @property int $line_count
 * @property bool $is_binary
 * @property bool $is_excluded
 * @property string|null $exclusion_reason
 * @property array<array-key, mixed>|null $framework_hints
 * @property array<array-key, mixed>|null $symbols_declared
 * @property array<array-key, mixed>|null $imports
 * @property string|null $mime_type
 * @property \Illuminate\Support\Carbon|null $file_modified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProjectFileChunk> $chunks
 * @property-read int|null $chunks_count
 * @property-read int $chunk_count
 * @property-read array $chunk_ids
 * @property-read string $directory
 * @property-read string $filename
 * @property-read \App\Models\Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile binary()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile byExtension(string $extension)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile byLanguage(string $language)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile excluded()
 * @method static \Database\Factories\ProjectFileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile inDirectory(string $directory)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile included()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile nonBinary()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereExclusionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereExtension($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereFileModifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereFrameworkHints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereImports($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereIsBinary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereIsExcluded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereLineCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereSha1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereSymbolsDeclared($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectFile withFrameworkHint(string $hint)
 * @mixin \Eloquent
 */
class ProjectFile extends Model
{
    /** @use HasFactory<ProjectFileFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'file_id',
        'path',
        'extension',
        'language',
        'size_bytes',
        'sha1',
        'line_count',
        'is_binary',
        'is_excluded',
        'exclusion_reason',
        'mime_type',
        'framework_hints',
        'symbols_declared',
        'imports',
        'file_modified_at',
    ];

    protected function casts(): array
    {
        return [
            'project_id' => 'string',
            'is_binary' => 'boolean',
            'is_excluded' => 'boolean',
            'framework_hints' => 'array',
            'symbols_declared' => 'array',
            'imports' => 'array',
            'file_modified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ProjectFileChunk::class, 'path', 'path')
            ->where('project_id', $this->project_id);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getDirectoryAttribute(): string
    {
        $dir = dirname($this->path);
        return $dir === '.' ? '(root)' : $dir;
    }

    public function getFilenameAttribute(): string
    {
        return basename($this->path);
    }

    public function getChunkCountAttribute(): int
    {
        return $this->chunks()->count();
    }

    public function getChunkIdsAttribute(): array
    {
        return $this->chunks()->orderBy('start_line')->pluck('chunk_id')->toArray();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeIncluded($query)
    {
        return $query->where('is_excluded', false);
    }

    public function scopeExcluded($query)
    {
        return $query->where('is_excluded', true);
    }

    public function scopeBinary($query)
    {
        return $query->where('is_binary', true);
    }

    public function scopeNonBinary($query)
    {
        return $query->where('is_binary', false);
    }

    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopeByExtension($query, string $extension)
    {
        return $query->where('extension', $extension);
    }

    public function scopeInDirectory($query, string $directory)
    {
        return $query->where('path', 'like', $directory . '/%');
    }

    public function scopeWithFrameworkHint($query, string $hint)
    {
        return $query->whereJsonContains('framework_hints', $hint);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    public function hasFrameworkHint(string $hint): bool
    {
        return in_array($hint, $this->framework_hints ?? [], true);
    }

    public function isChunked(): bool
    {
        return $this->chunks()->count() > 0;
    }

    public function getFullPath(Project $project): string
    {
        return $project->repo_path . '/' . $this->path;
    }

    public function getContent(Project $project): ?string
    {
        $fullPath = $this->getFullPath($project);

        if (!file_exists($fullPath)) {
            return null;
        }

        return @file_get_contents($fullPath);
    }

    public static function generateFileId(string $path): string
    {
        return 'f_' . substr(sha1($path), 0, 12);
    }
}
