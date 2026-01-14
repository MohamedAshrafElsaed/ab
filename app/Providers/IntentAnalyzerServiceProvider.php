<?php

namespace App\Providers;

use App\Services\AI\IntentAnalyzerService;
use Illuminate\Support\ServiceProvider;

class IntentAnalyzerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/intent_analyzer.php',
            'intent_analyzer'
        );

        $this->app->singleton(IntentAnalyzerService::class, function () {
            return new IntentAnalyzerService();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/intent_analyzer.php' => config_path('intent_analyzer.php'),
        ], 'intent-analyzer-config');

        $this->publishes([
            __DIR__ . '/../../resources/prompts/intent_analyzer.md' => resource_path('prompts/intent_analyzer.md'),
        ], 'intent-analyzer-prompts');
    }
}
