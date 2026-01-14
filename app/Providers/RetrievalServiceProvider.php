<?php

namespace App\Providers;

use App\Services\AI\ContextRetrievalService;
use App\Services\AI\RetrievalCacheService;
use App\Services\AI\RouteAnalyzerService;
use App\Services\AI\SymbolGraphService;
use App\Services\AskAI\SensitiveContentRedactor;
use Illuminate\Support\ServiceProvider;

class RetrievalServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/retrieval.php',
            'retrieval'
        );

        // Register SymbolGraphService as singleton
        $this->app->singleton(SymbolGraphService::class, function ($app) {
            return new SymbolGraphService();
        });

        // Register RouteAnalyzerService as singleton
        $this->app->singleton(RouteAnalyzerService::class, function ($app) {
            return new RouteAnalyzerService();
        });

        // Register RetrievalCacheService as singleton
        $this->app->singleton(RetrievalCacheService::class, function ($app) {
            return new RetrievalCacheService();
        });

        // Register ContextRetrievalService
        $this->app->singleton(ContextRetrievalService::class, function ($app) {
            return new ContextRetrievalService(
                $app->make(SymbolGraphService::class),
                $app->make(RouteAnalyzerService::class),
                $app->make(SensitiveContentRedactor::class),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/retrieval.php' => config_path('retrieval.php'),
        ], 'retrieval-config');
    }
}
