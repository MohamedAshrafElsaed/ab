<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->string('current_stage')->nullable();
            $table->unsignedTinyInteger('stage_percent')->default(0);
            $table->string('commit_sha', 40)->nullable();
            $table->string('trigger')->default('manual'); // manual, webhook, schedule
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_scans');
    }
};
