<?php

use App\Enums\AgentMessageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('agent_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system'])->default('user');
            $table->longText('content');
            $table->enum('message_type', array_column(AgentMessageType::cases(), 'value'))->default('text');
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
