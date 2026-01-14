<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->uuid('conversation_id')->index();
            $table->foreignUuid('intent_analysis_id')->nullable()->constrained('intent_analyses')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('title', 255);
            $table->text('description');
            $table->json('plan_data');
            $table->json('file_operations');
            $table->string('estimated_complexity', 20)->default('medium');
            $table->unsignedInteger('estimated_files_affected')->default(0);
            $table->json('risks')->nullable();
            $table->json('prerequisites')->nullable();
            $table->text('user_feedback')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('execution_started_at')->nullable();
            $table->timestamp('execution_completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'conversation_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_plans');
    }
};
