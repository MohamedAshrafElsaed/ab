<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GitHubController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Settings\SocialAccountController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// OAuth Routes
Route::get('/auth/{provider}', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['github', 'google'])
    ->name('auth.social');

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['github', 'google'])
    ->name('auth.social.callback');

// Webhook Routes (no auth required, but signature verified)
Route::post('/webhooks/github', [WebhookController::class, 'github'])
    ->name('webhooks.github');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/github/connect', [GitHubController::class, 'connect'])->name('github.connect');
    Route::get('/github/callback', [GitHubController::class, 'callback'])->name('github.callback');

    // Project routes
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::get('/projects/confirm', [ProjectController::class, 'confirm'])->name('projects.confirm');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/scan-status', [ProjectController::class, 'scanStatus'])->name('projects.scan-status');
    Route::post('/projects/{project}/retry-scan', [ProjectController::class, 'retryScan'])->name('projects.retry-scan');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Ask AI page (Inertia)
    Route::get('/projects/{project}/ask', [ProjectController::class, 'askAI'])->name('projects.ask');

    Route::delete('/settings/social-accounts/{provider}', [SocialAccountController::class, 'destroy'])
        ->whereIn('provider', ['github', 'google'])
        ->name('settings.social-accounts.destroy');
});

require __DIR__ . '/settings.php';
