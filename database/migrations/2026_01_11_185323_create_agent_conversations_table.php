<?php

use App\Enums\ConversationPhase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255)->nullable();
            $table->enum('status', ['active', 'paused', 'completed', 'failed'])->default('active');
            $table->enum('current_phase', array_column(ConversationPhase::cases(), 'value'))->default('intake');
            $table->foreignUuid('current_intent_id')->nullable()->constrained('intent_analyses')->nullOnDelete();
            $table->foreignUuid('current_plan_id')->nullable()->constrained('execution_plans')->nullOnDelete();
            $table->json('context_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversations');
    }
};
