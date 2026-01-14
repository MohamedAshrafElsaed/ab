<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add fulltext index on project_files for path searching
        if (!$this->indexExists('project_files', 'project_files_path_fulltext')) {
            DB::statement('ALTER TABLE `project_files` ADD FULLTEXT INDEX `project_files_path_fulltext` (`path`)');
        }

        // Add composite index for chunk retrieval queries
        if (!$this->indexExists('project_file_chunks', 'project_file_chunks_retrieval_idx')) {
            Schema::table('project_file_chunks', function ($table) {
                $table->index(['project_id', 'path', 'start_line', 'end_line'], 'project_file_chunks_retrieval_idx');
            });
        }

        // Add index for symbols_declared JSON queries (MySQL 8.0+)
        if (!$this->indexExists('project_file_chunks', 'project_file_chunks_project_chunk_idx')) {
            Schema::table('project_file_chunks', function ($table) {
                $table->index(['project_id', 'chunk_id'], 'project_file_chunks_project_chunk_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('project_files', 'project_files_path_fulltext')) {
            DB::statement('ALTER TABLE `project_files` DROP INDEX `project_files_path_fulltext`');
        }

        Schema::table('project_file_chunks', function ($table) {
            $table->dropIndex('project_file_chunks_retrieval_idx');
            $table->dropIndex('project_file_chunks_project_chunk_idx');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
