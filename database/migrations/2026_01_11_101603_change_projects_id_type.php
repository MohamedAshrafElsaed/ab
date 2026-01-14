<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add UUID column to projects
        Schema::table('projects', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Step 2: Generate UUIDs for existing projects
        DB::table('projects')->orderBy('id')->each(function ($project) {
            DB::table('projects')->where('id', $project->id)->update(['uuid' => Str::uuid()]);
        });

        // Step 3: Add UUID columns to related tables
        Schema::table('project_scans', function (Blueprint $table) {
            $table->uuid('project_uuid')->nullable()->after('project_id');
        });

        Schema::table('project_files', function (Blueprint $table) {
            $table->uuid('project_uuid')->nullable()->after('project_id');
        });

        Schema::table('project_file_chunks', function (Blueprint $table) {
            $table->uuid('project_uuid')->nullable()->after('project_id');
        });

        // Step 4: Populate UUID columns in related tables
        DB::table('projects')->orderBy('id')->each(function ($project) {
            DB::table('project_scans')
                ->where('project_id', $project->id)
                ->update(['project_uuid' => $project->uuid]);

            DB::table('project_files')
                ->where('project_id', $project->id)
                ->update(['project_uuid' => $project->uuid]);

            DB::table('project_file_chunks')
                ->where('project_id', $project->id)
                ->update(['project_uuid' => $project->uuid]);
        });

        // Step 5: Drop old foreign keys
        Schema::table('project_scans', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('project_files', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('project_file_chunks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        // Step 6: Drop old indexes that reference project_id
        Schema::table('project_scans', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
        });

        Schema::table('project_files', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'extension']);
            $table->dropIndex(['project_id', 'sha1']);
            $table->dropIndex(['project_id', 'is_excluded']);
            $table->dropIndex(['project_id', 'language']);
        });
        DB::statement('ALTER TABLE `project_files` DROP INDEX `project_files_project_id_path_index`');

        Schema::table('project_file_chunks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'chunk_id']);
            $table->dropIndex(['project_id', 'chunk_sha1']);
        });
        DB::statement('ALTER TABLE `project_file_chunks` DROP INDEX `project_file_chunks_project_id_path_index`');

        // Step 7: Drop old project_id columns
        Schema::table('project_scans', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        Schema::table('project_files', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        Schema::table('project_file_chunks', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        // Step 8: Rename uuid columns to project_id
        Schema::table('project_scans', function (Blueprint $table) {
            $table->renameColumn('project_uuid', 'project_id');
        });

        Schema::table('project_files', function (Blueprint $table) {
            $table->renameColumn('project_uuid', 'project_id');
        });

        Schema::table('project_file_chunks', function (Blueprint $table) {
            $table->renameColumn('project_uuid', 'project_id');
        });

        // Step 9: Drop old id and rename uuid to id in projects
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('uuid', 'id');
        });

        // Step 10: Make id primary key
        DB::statement('ALTER TABLE `projects` ADD PRIMARY KEY (`id`)');

        // Step 11: Make project_id columns not nullable and add foreign keys
        Schema::table('project_scans', function (Blueprint $table) {
            $table->uuid('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::table('project_files', function (Blueprint $table) {
            $table->uuid('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'extension']);
            $table->index(['project_id', 'sha1']);
            $table->index(['project_id', 'is_excluded']);
            $table->index(['project_id', 'language']);
        });
        DB::statement('ALTER TABLE `project_files` ADD INDEX `project_files_project_id_path_index` (`project_id`, `path`(255))');

        Schema::table('project_file_chunks', function (Blueprint $table) {
            $table->uuid('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'chunk_id']);
            $table->index(['project_id', 'chunk_sha1']);
        });
        DB::statement('ALTER TABLE `project_file_chunks` ADD INDEX `project_file_chunks_project_id_path_index` (`project_id`, `path`(255))');
    }

    public function down(): void
    {
        // This migration is not safely reversible due to data loss
        // You would need to restore from backup
        throw new \Exception('This migration cannot be reversed. Restore from backup if needed.');
    }
};
