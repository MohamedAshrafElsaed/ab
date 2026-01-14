<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intent_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->uuid('conversation_id')->index();
            $table->uuid('message_id')->index();
            $table->text('raw_input');
            $table->string('intent_type', 50);
            $table->decimal('confidence_score', 3, 2)->default(0.00);
            $table->json('extracted_entities')->nullable();
            $table->json('domain_classification')->nullable();
            $table->string('complexity_estimate', 20)->default('medium');
            $table->boolean('requires_clarification')->default(false);
            $table->json('clarification_questions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'conversation_id']);
            $table->index(['intent_type', 'confidence_score']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intent_analyses');
    }
};
