<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            // Use UUID foreign key for SQLite, regular foreignId for MySQL
            if (DB::connection()->getDriverName() === 'sqlite') {
                $table->uuid('project_id');
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            } else {
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            }
            $table->string('path', 512);
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha1', 40)->nullable();
            $table->unsignedInteger('line_count')->default(0);
            $table->boolean('is_binary')->default(false);
            $table->string('mime_type', 128)->nullable();
            $table->timestamp('file_modified_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'extension']);
            $table->index(['project_id', 'sha1']);
        });

        // Add prefix index for path (MySQL only - SQLite doesn't support prefix indexes)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `project_files` ADD INDEX `project_files_project_id_path_index` (`project_id`, `path`(255))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};
