<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // google, github
            $table->string('provider_id');
            $table->string('provider_email')->nullable();
            $table->string('avatar')->nullable();
            $table->json('provider_data')->nullable(); // Store extra data
            $table->timestamps();

            // Prevent duplicate provider accounts
            $table->unique(['provider', 'provider_id']);
            // One provider per user
            $table->unique(['user_id', 'provider']);
        });

        // Remove old columns from users table if they exist
        if (Schema::hasColumn('users', 'provider')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['provider', 'provider_id', 'avatar']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
