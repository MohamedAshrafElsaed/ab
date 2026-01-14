<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('current_stage')->nullable()->after('status');
            $table->unsignedTinyInteger('stage_percent')->default(0)->after('current_stage');
            $table->timestamp('scanned_at')->nullable()->after('stage_percent');
            $table->string('last_commit_sha', 40)->nullable()->after('scanned_at');
            $table->text('last_error')->nullable()->after('last_commit_sha');
            $table->json('stack_info')->nullable()->after('last_error');
            $table->unsignedBigInteger('total_files')->default(0)->after('stack_info');
            $table->unsignedBigInteger('total_lines')->default(0)->after('total_files');
            $table->unsignedBigInteger('total_size_bytes')->default(0)->after('total_lines');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'current_stage',
                'stage_percent',
                'scanned_at',
                'last_commit_sha',
                'last_error',
                'stack_info',
                'total_files',
                'total_lines',
                'total_size_bytes',
            ]);
        });
    }
};
