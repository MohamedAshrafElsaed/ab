<?php

namespace App\Providers;

use App\Services\AskAI\AskAIService;
use App\Services\AskAI\PromptBuilder;
use App\Services\AskAI\ResponseFormatter;
use App\Services\AskAI\RetrievalService;
use App\Services\AskAI\SensitiveContentRedactor;
use Illuminate\Support\ServiceProvider;

class AskAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SensitiveContentRedactor::class, function () {
            return new SensitiveContentRedactor();
        });

        $this->app->singleton(RetrievalService::class, function ($app) {
            return new RetrievalService(
                $app->make(SensitiveContentRedactor::class)
            );
        });

        $this->app->singleton(PromptBuilder::class, function () {
            return new PromptBuilder();
        });

        $this->app->singleton(ResponseFormatter::class, function ($app) {
            return new ResponseFormatter(
                $app->make(PromptBuilder::class)
            );
        });

        $this->app->singleton(AskAIService::class, function ($app) {
            return new AskAIService(
                $app->make(RetrievalService::class),
                $app->make(PromptBuilder::class),
                $app->make(ResponseFormatter::class)
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
