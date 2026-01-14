<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // =====================================================================
        // PROJECT FILES TABLE - Add new metadata fields
        // =====================================================================
        Schema::table('project_files', function (Blueprint $table) {
            // Stable file identifier (f_ + 12 char hash)
            $table->string('file_id', 20)->nullable()->after('id');

            // Language detection result
            $table->string('language', 50)->nullable()->after('extension');

            // Exclusion tracking
            $table->boolean('is_excluded')->default(false)->after('is_binary');
            $table->string('exclusion_reason', 255)->nullable()->after('is_excluded');

            // Framework hints (JSON array of strings like ["laravel", "livewire"])
            $table->json('framework_hints')->nullable()->after('exclusion_reason');

            // Symbol extraction (JSON arrays)
            $table->json('symbols_declared')->nullable()->after('framework_hints');
            $table->json('imports')->nullable()->after('symbols_declared');

            // Indexes for common queries
            $table->index('file_id');
            $table->index('language');
            $table->index('is_excluded');
            $table->index(['project_id', 'is_excluded']);
            $table->index(['project_id', 'language']);
        });

        // =====================================================================
        // PROJECT FILE CHUNKS TABLE - Add new chunk metadata
        // =====================================================================
        Schema::table('project_file_chunks', function (Blueprint $table) {
            // Backward compatibility for old chunk_id format (chunk_0001)
            $table->string('old_chunk_id', 20)->nullable()->after('chunk_id');

            // Sequential index within file (0, 1, 2, ...)
            $table->unsignedInteger('chunk_index')->default(0)->after('end_line');

            // Chunk content hash for change detection
            $table->string('chunk_sha1', 40)->nullable()->after('sha1');

            // Flag for single-chunk files (entire file in one chunk)
            $table->boolean('is_complete_file')->default(false)->after('chunk_sha1');

            // Symbol extraction for chunks (JSON arrays)
            $table->json('symbols_declared')->nullable()->after('is_complete_file');
            $table->json('symbols_used')->nullable()->after('symbols_declared');
            $table->json('imports')->nullable()->after('symbols_used');
            $table->json('references')->nullable()->after('imports');

            // Indexes
            $table->index('old_chunk_id');
            $table->index('chunk_sha1');
            $table->index('chunk_index');
            $table->index(['project_id', 'chunk_sha1']);
        });

        // =====================================================================
        // PROJECTS TABLE - Add version tracking and branch selection
        // =====================================================================
        Schema::table('projects', function (Blueprint $table) {
            // Selected branch (if different from default_branch)
            $table->string('selected_branch', 255)->nullable()->after('default_branch');

            // Parent commit for incremental scans
            $table->string('parent_commit_sha', 40)->nullable()->after('last_commit_sha');

            // Scanner output version for migration tracking (e.g., "2.0.0")
            $table->string('scan_output_version', 10)->nullable()->after('parent_commit_sha');

            // Exclusion rules version hash for cache invalidation
            $table->string('exclusion_rules_version', 40)->nullable()->after('scan_output_version');

            // Track when project was last migrated
            $table->timestamp('last_migration_at')->nullable()->after('exclusion_rules_version');
        });

        // =====================================================================
        // PROJECT SCANS TABLE - Add detailed scan metrics
        // =====================================================================
        Schema::table('project_scans', function (Blueprint $table) {
            // Scanner version used (e.g., "2.0.0")
            $table->string('scanner_version', 10)->nullable()->after('status');

            // Is this an incremental scan?
            $table->boolean('is_incremental')->default(false)->after('scanner_version');

            // Previous commit SHA for incremental scans
            $table->string('previous_commit_sha', 40)->nullable()->after('is_incremental');

            // Detailed counts
            $table->unsignedInteger('files_scanned')->default(0)->after('previous_commit_sha');
            $table->unsignedInteger('files_excluded')->default(0)->after('files_scanned');
            $table->unsignedInteger('chunks_created')->default(0)->after('files_excluded');
            $table->unsignedBigInteger('total_lines')->default(0)->after('chunks_created');
            $table->unsignedBigInteger('total_bytes')->default(0)->after('total_lines');

            // Duration tracking in milliseconds
            $table->unsignedInteger('duration_ms')->nullable()->after('total_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_files', function (Blueprint $table) {
            $table->dropIndex(['file_id']);
            $table->dropIndex(['language']);
            $table->dropIndex(['is_excluded']);
            $table->dropIndex(['project_id', 'is_excluded']);
            $table->dropIndex(['project_id', 'language']);

            $table->dropColumn([
                'file_id',
                'language',
                'is_excluded',
                'exclusion_reason',
                'framework_hints',
                'symbols_declared',
                'imports',
            ]);
        });

        Schema::table('project_file_chunks', function (Blueprint $table) {
            $table->dropIndex(['old_chunk_id']);
            $table->dropIndex(['chunk_sha1']);
            $table->dropIndex(['chunk_index']);
            $table->dropIndex(['project_id', 'chunk_sha1']);

            $table->dropColumn([
                'old_chunk_id',
                'chunk_index',
                'chunk_sha1',
                'is_complete_file',
                'symbols_declared',
                'symbols_used',
                'imports',
                'references',
            ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'selected_branch',
                'parent_commit_sha',
                'scan_output_version',
                'exclusion_rules_version',
                'last_migration_at',
            ]);
        });

        Schema::table('project_scans', function (Blueprint $table) {
            $table->dropColumn([
                'scanner_version',
                'is_incremental',
                'previous_commit_sha',
                'files_scanned',
                'files_excluded',
                'chunks_created',
                'total_lines',
                'total_bytes',
                'duration_ms',
            ]);
        });
    }
};
