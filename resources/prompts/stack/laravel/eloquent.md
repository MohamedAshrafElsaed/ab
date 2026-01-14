# Laravel Eloquent Patterns

<eloquent_conventions>

## Model Structure
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    use HasFactory;

    // 1. Properties
    protected $fillable = [
        'user_id',
        'name',
        'repo_url',
        'status',
        'stack_info',
    ];

    protected $casts = [
        'stack_info' => 'array',
        'scanned_at' => 'datetime',
        'is_public' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    // 2. Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ProjectFileChunk::class);
    }

    public function latestScan(): HasOne
    {
        return $this->hasOne(ProjectScan::class)->latestOfMany();
    }

    // 3. Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeWithStack(Builder $query, string $tech): Builder
    {
        return $query->whereJsonContains('stack_info->frontend', $tech);
    }

    // 4. Accessors & Mutators
    protected function repoName(): Attribute
    {
        return Attribute::get(fn () => basename($this->repo_url));
    }

    protected function isLaravel(): Attribute
    {
        return Attribute::get(
            fn () => ($this->stack_info['framework'] ?? null) === 'laravel'
        );
    }

    // 5. Methods
    public function markAsReady(): void
    {
        $this->update(['status' => 'ready', 'scanned_at' => now()]);
    }

    public function updateStats(int $files, int $lines, int $bytes): void
    {
        $this->update([
            'total_files' => $files,
            'total_lines' => $lines,
            'total_size_bytes' => $bytes,
        ]);
    }
}
```

## Relationship Patterns

### One-to-Many
```php
// Parent model
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}

// Child model
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

### Many-to-Many
```php
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class)
        ->withPivot('assigned_at')
        ->withTimestamps();
}
```

### Polymorphic
```php
// Commentable models
public function comments(): MorphMany
{
    return $this->morphMany(Comment::class, 'commentable');
}

// Comment model
public function commentable(): MorphTo
{
    return $this->morphTo();
}
```

## Query Patterns

### Eager Loading (Prevent N+1)
```php
// Bad - causes N+1
$projects = Project::all();
foreach ($projects as $project) {
    echo $project->user->name; // Query per iteration
}

// Good - eager load
$projects = Project::with('user')->get();
foreach ($projects as $project) {
    echo $project->user->name; // No additional queries
}

// Nested eager loading
$projects = Project::with([
    'user',
    'files' => fn($q) => $q->where('is_excluded', false),
    'files.chunks',
])->get();
```

### Query Scopes
```php
// Chain scopes for readable queries
$projects = Project::query()
    ->active()
    ->forUser($user)
    ->withStack('vue')
    ->latest()
    ->paginate(20);
```

### Chunking Large Datasets
```php
// Process large datasets without memory issues
Project::where('status', 'pending')
    ->chunk(100, function ($projects) {
        foreach ($projects as $project) {
            ProcessProject::dispatch($project);
        }
    });

// Or use lazy loading
Project::where('status', 'pending')
    ->lazy()
    ->each(fn($project) => $project->process());
```

### Aggregates
```php
// Efficient counting
$count = Project::where('user_id', $userId)->count();

// Subquery selects
$users = User::withCount('projects')
    ->withSum('projects', 'total_lines')
    ->get();
```

## Best Practices

### Use Transactions
```php
DB::transaction(function () use ($data) {
    $project = Project::create($data);
    $project->files()->createMany($filesData);
    return $project;
});
```

### Mass Assignment Protection
```php
// Always define $fillable
protected $fillable = ['name', 'email'];

// Or use $guarded for inverse
protected $guarded = ['id', 'is_admin'];
```

### Soft Deletes
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;
}

// Query includes soft deleted
Project::withTrashed()->get();

// Only soft deleted
Project::onlyTrashed()->get();

// Restore
$project->restore();

// Force delete
$project->forceDelete();
```

### Events & Observers
```php
// In model boot method
protected static function booted(): void
{
    static::creating(function (Project $project) {
        $project->uuid = Str::uuid();
    });

    static::deleted(function (Project $project) {
        $project->files()->delete();
    });
}

// Or use Observer class
class ProjectObserver
{
    public function created(Project $project): void
    {
        ScanProject::dispatch($project);
    }
}
```

</eloquent_conventions>
