<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('execution_plan_id');
            $table->integer('file_operation_index');
            $table->string('operation_type', 20);
            $table->string('file_path', 500);
            $table->string('new_file_path', 500)->nullable();
            $table->string('status', 30)->default('pending');
            $table->longText('original_content')->nullable();
            $table->longText('new_content')->nullable();
            $table->longText('diff')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('user_approved')->default(false);
            $table->boolean('auto_approved')->default(false);
            $table->string('backup_path', 500)->nullable();
            $table->timestamp('execution_started_at')->nullable();
            $table->timestamp('execution_completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('execution_plan_id')
                ->references('id')
                ->on('execution_plans')
                ->onDelete('cascade');

            $table->index(['execution_plan_id', 'file_operation_index']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_executions');
    }
};
