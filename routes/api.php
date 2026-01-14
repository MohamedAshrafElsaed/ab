<?php

use App\Http\Controllers\Api\ProjectAskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // Ask AI about project
    Route::post('/projects/{project}/ask', [ProjectAskController::class, 'ask'])
        ->name('api.projects.ask');

    Route::get('/projects/{project}/ask/context', [ProjectAskController::class, 'context'])
        ->name('api.projects.ask.context');

    // AI Agent Conversation Routes
    Route::prefix('projects/{project}/ai')
        ->group(base_path('routes/ai.php'));
});
