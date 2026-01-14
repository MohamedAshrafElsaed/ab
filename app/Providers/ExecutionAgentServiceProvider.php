<?php

namespace App\Providers;

use App\Services\AI\ExecutionAgentService;
use App\Services\Files\DiffGeneratorService;
use App\Services\Files\FileWriterService;
use App\Services\Prompts\PromptTemplateService;
use Illuminate\Support\ServiceProvider;

class ExecutionAgentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/execution.php',
            'execution'
        );

        $this->app->singleton(DiffGeneratorService::class, function ($app) {
            $contextLines = config('execution.diff.context_lines', 3);
            return new DiffGeneratorService($contextLines);
        });

        $this->app->singleton(FileWriterService::class, function ($app) {
            return new FileWriterService();
        });

        $this->app->singleton(ExecutionAgentService::class, function ($app) {
            return new ExecutionAgentService(
                $app->make(FileWriterService::class),
                $app->make(DiffGeneratorService::class),
                $app->make(PromptTemplateService::class),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/execution.php' => config_path('execution.php'),
        ], 'execution-config');
    }
}
