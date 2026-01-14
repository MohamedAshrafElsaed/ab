<?php

namespace App\Providers;

use App\Services\AI\ContextRetrievalService;
use App\Services\AI\PlanningAgentService;
use App\Services\Prompts\PromptTemplateService;
use Illuminate\Support\ServiceProvider;

class PlanningAgentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/planning.php',
            'planning'
        );

        // Register PlanningAgentService as singleton
        $this->app->singleton(PlanningAgentService::class, function ($app) {
            return new PlanningAgentService(
                $app->make(ContextRetrievalService::class),
                $app->make(PromptTemplateService::class),
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
            __DIR__ . '/../../config/planning.php' => config_path('planning.php'),
        ], 'planning-config');

        // Publish user prompt templates
        $this->publishes([
            __DIR__ . '/../../resources/prompts/user/planning_request.md' => resource_path('prompts/user/planning_request.md'),
        ], 'planning-prompts');
    }
}
