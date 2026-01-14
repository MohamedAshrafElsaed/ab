<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_file_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('chunk_id', 64);
            $table->string('path', 512);
            $table->unsignedInteger('start_line')->default(1);
            $table->unsignedInteger('end_line')->nullable();
            $table->string('sha1', 40)->nullable();
            $table->string('chunk_file_path', 512)->nullable();
            $table->unsignedInteger('chunk_size_bytes')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'chunk_id']);
        });

        // Add prefix index for path (MySQL limitation)
        DB::statement('ALTER TABLE `project_file_chunks` ADD INDEX `project_file_chunks_project_id_path_index` (`project_id`, `path`(255))');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_file_chunks');
    }
};
