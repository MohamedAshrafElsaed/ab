<?php

namespace App\Providers;

use App\Services\AI\ContextRetrievalService;
use App\Services\AI\ExecutionAgentService;
use App\Services\AI\IntentAnalyzerService;
use App\Services\AI\OrchestratorService;
use App\Services\AI\PlanningAgentService;
use App\Services\Prompts\PromptComposer;
use App\Services\Prompts\PromptTemplateService;
use Illuminate\Support\ServiceProvider;

class OrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/orchestrator.php',
            'orchestrator'
        );

        $this->app->singleton(OrchestratorService::class, function ($app) {
            return new OrchestratorService(
                $app->make(IntentAnalyzerService::class),
                $app->make(ContextRetrievalService::class),
                $app->make(PlanningAgentService::class),
                $app->make(ExecutionAgentService::class),
                $app->make(PromptTemplateService::class),
                $app->make(PromptComposer::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/orchestrator.php' => config_path('orchestrator.php'),
        ], 'orchestrator-config');

        $this->publishes([
            __DIR__ . '/../../resources/prompts/system/orchestrator.md' => resource_path('prompts/system/orchestrator.md'),
        ], 'orchestrator-prompts');
    }
}
