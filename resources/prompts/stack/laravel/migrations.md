# Laravel Migration Patterns

<migration_conventions>

## Migration Structure
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('repo_url')->unique();
            $table->string('status')->default('pending');
            $table->json('stack_info')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

## Common Column Types

### Strings & Text
```php
$table->string('name');                    // VARCHAR(255)
$table->string('code', 100);               // VARCHAR(100)
$table->text('description');               // TEXT
$table->mediumText('content');             // MEDIUMTEXT
$table->longText('body');                  // LONGTEXT
$table->char('country_code', 2);           // CHAR(2)
```

### Numbers
```php
$table->integer('count');                  // INT
$table->unsignedInteger('views');          // UNSIGNED INT
$table->bigInteger('large_number');        // BIGINT
$table->tinyInteger('priority');           // TINYINT
$table->decimal('price', 10, 2);           // DECIMAL(10,2)
$table->float('rating', 3, 1);             // FLOAT(3,1)
```

### Dates & Times
```php
$table->timestamp('published_at');         // TIMESTAMP
$table->timestamps();                      // created_at, updated_at
$table->softDeletes();                     // deleted_at
$table->date('birth_date');                // DATE
$table->dateTime('starts_at');             // DATETIME
$table->time('opening_time');              // TIME
```

### Special Types
```php
$table->json('settings');                  // JSON
$table->boolean('is_active');              // BOOLEAN
$table->uuid('uuid');                      // UUID
$table->ulid('ulid');                      // ULID
$table->ipAddress('visitor_ip');           // VARCHAR for IP
$table->macAddress('device_mac');          // VARCHAR for MAC
$table->enum('status', ['pending', 'active', 'completed']);
```

## Relationships

### Foreign Keys
```php
// Simple foreign key with cascade
$table->foreignId('user_id')->constrained()->cascadeOnDelete();

// With custom table/column
$table->foreignId('author_id')
    ->constrained('users')
    ->onDelete('set null')
    ->onUpdate('cascade');

// Nullable foreign key
$table->foreignId('category_id')->nullable()->constrained();

// Custom foreign key name
$table->foreign('user_id', 'posts_author_fk')->references('id')->on('users');
```

### Polymorphic Relations
```php
$table->morphs('commentable');  // Creates commentable_id and commentable_type
$table->nullableMorphs('taggable');
$table->uuidMorphs('trackable'); // For UUID primary keys
```

### Pivot Tables
```php
Schema::create('project_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role')->default('member');
    $table->timestamps();

    $table->unique(['project_id', 'user_id']);
});
```

## Indexes

### Basic Indexes
```php
$table->index('email');                    // Single column
$table->index(['user_id', 'created_at']);  // Composite
$table->unique('email');                   // Unique constraint
$table->primary(['order_id', 'product_id']); // Composite primary
```

### Full-Text Search
```php
$table->fullText('content');
$table->fullText(['title', 'body']);
```

### Prefix Index (for long columns)
```php
// MySQL has index length limits
$table->index([DB::raw('path(191)')], 'files_path_index');
// Or use raw SQL
Schema::table('files', function (Blueprint $table) {
    DB::statement('CREATE INDEX files_path_index ON files (path(191))');
});
```

## Modifying Tables

### Adding Columns
```php
Schema::table('projects', function (Blueprint $table) {
    $table->string('slug')->after('name');
    $table->boolean('is_public')->default(false)->after('status');
});
```

### Modifying Columns
```php
// Requires doctrine/dbal package
Schema::table('projects', function (Blueprint $table) {
    $table->string('name', 500)->change();
    $table->string('status')->default('active')->change();
});
```

### Renaming & Dropping
```php
Schema::table('projects', function (Blueprint $table) {
    $table->renameColumn('name', 'title');
    $table->dropColumn('deprecated_field');
    $table->dropIndex('projects_email_index');
    $table->dropForeign('projects_user_id_foreign');
});
```

## Best Practices

### Naming Conventions
```php
// Tables: plural, snake_case
'projects', 'project_files', 'user_settings'

// Pivot tables: singular, alphabetical order
'project_user', 'category_post'

// Foreign keys: singular_table_id
'user_id', 'project_id', 'parent_id'
```

### Safe Migrations
```php
// Always check before creating
if (!Schema::hasTable('projects')) {
    Schema::create('projects', ...);
}

// Check column exists
if (!Schema::hasColumn('projects', 'slug')) {
    Schema::table('projects', fn($t) => $t->string('slug'));
}

// Reversible migrations
public function down(): void
{
    // Always implement proper rollback
    Schema::dropIfExists('projects');
}
```

### Data Migrations
```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('full_name')->nullable();
    });

    // Migrate existing data
    DB::table('users')->update([
        'full_name' => DB::raw("CONCAT(first_name, ' ', last_name)")
    ]);

    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['first_name', 'last_name']);
    });
}
```

</migration_conventions>
