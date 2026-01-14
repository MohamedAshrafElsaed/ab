<?php

use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\ProjectAskController;
use App\Http\Controllers\Api\V1\ProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| These routes are loaded with the 'api' middleware group and prefixed
| with 'v1'. All endpoints here are versioned for backwards compatibility.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Project endpoints
    Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);

    Route::prefix('projects/{project}')->group(function () {
        // Ask AI about project
        Route::post('/ask', [ProjectAskController::class, 'ask'])
            ->name('v1.projects.ask')
            ->middleware('throttle:ask-ai');

        Route::get('/ask/context', [ProjectAskController::class, 'context'])
            ->name('v1.projects.ask.context');

        // AI Agent Conversation Routes
        Route::prefix('conversations')->group(function () {
            Route::get('/', [ConversationController::class, 'index'])
                ->name('v1.conversations.index');

            Route::post('/', [ConversationController::class, 'store'])
                ->name('v1.conversations.store')
                ->middleware('throttle:conversation');

            Route::get('{conversation}', [ConversationController::class, 'show'])
                ->name('v1.conversations.show');

            Route::post('{conversation}/messages', [ConversationController::class, 'sendMessage'])
                ->name('v1.conversations.message')
                ->middleware('throttle:conversation-message');

            Route::get('{conversation}/messages', [ConversationController::class, 'messages'])
                ->name('v1.conversations.messages');

            Route::post('{conversation}/approve', [ConversationController::class, 'handleApproval'])
                ->name('v1.conversations.approve');

            Route::post('{conversation}/files/{execution}/approve', [ConversationController::class, 'handleFileApproval'])
                ->name('v1.conversations.file-approve');

            Route::post('{conversation}/cancel', [ConversationController::class, 'cancel'])
                ->name('v1.conversations.cancel');

            Route::post('{conversation}/resume', [ConversationController::class, 'resume'])
                ->name('v1.conversations.resume');

            Route::delete('{conversation}', [ConversationController::class, 'destroy'])
                ->name('v1.conversations.destroy');
        });
    });
});
