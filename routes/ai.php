<?php

use App\Http\Controllers\AI\ConversationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Agent Routes
|--------------------------------------------------------------------------
|
| Routes for the AI agent conversation system. These should be included
| in routes/api.php with the appropriate middleware and prefix.
|
| Example in routes/api.php:
|   Route::middleware('auth:sanctum')->prefix('projects/{project}/ai')->group(
|       base_path('routes/ai.php')
|   );
|
*/

Route::prefix('conversations')->group(function () {
    // List conversations for a project
    Route::get('/', [ConversationController::class, 'index'])
        ->name('ai.conversations.index');

    // Start a new conversation
    Route::post('/', [ConversationController::class, 'store'])
        ->name('ai.conversations.store');

    // Get conversation details and state
    Route::get('{conversation}', [ConversationController::class, 'show'])
        ->name('ai.conversations.show');

    // Send a message (streaming)
    Route::post('{conversation}/messages', [ConversationController::class, 'sendMessage'])
        ->name('ai.conversations.message');

    // Get paginated messages
    Route::get('{conversation}/messages', [ConversationController::class, 'messages'])
        ->name('ai.conversations.messages');

    // Approve or reject plan
    Route::post('{conversation}/approve', [ConversationController::class, 'handleApproval'])
        ->name('ai.conversations.approve');

    // Approve or skip file during execution
    Route::post('{conversation}/files/{execution}/approve', [ConversationController::class, 'handleFileApproval'])
        ->name('ai.conversations.file-approve');

    // Cancel current operation
    Route::post('{conversation}/cancel', [ConversationController::class, 'cancel'])
        ->name('ai.conversations.cancel');

    // Resume paused conversation
    Route::post('{conversation}/resume', [ConversationController::class, 'resume'])
        ->name('ai.conversations.resume');

    // Delete conversation
    Route::delete('{conversation}', [ConversationController::class, 'destroy'])
        ->name('ai.conversations.destroy');
});
