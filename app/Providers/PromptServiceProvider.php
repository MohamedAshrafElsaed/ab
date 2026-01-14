<?php

namespace App\Providers;

use App\Services\Prompts\PromptComposer;
use App\Services\Prompts\PromptTemplateService;
use Illuminate\Support\ServiceProvider;

class PromptServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/prompts.php',
            'prompts'
        );

        $this->app->singleton(PromptTemplateService::class, function () {
            return new PromptTemplateService();
        });

        $this->app->singleton(PromptComposer::class, function ($app) {
            return new PromptComposer(
                $app->make(PromptTemplateService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/prompts.php' => config_path('prompts.php'),
        ], 'prompts-config');

        $this->publishes([
            __DIR__ . '/../../resources/prompts' => resource_path('prompts'),
        ], 'prompts-templates');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\PromptCacheClearCommand::class,
            ]);
        }
    }
}
