<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('github');
            $table->string('repo_full_name');
            $table->string('repo_id')->nullable();
            $table->string('default_branch');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'repo_full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
