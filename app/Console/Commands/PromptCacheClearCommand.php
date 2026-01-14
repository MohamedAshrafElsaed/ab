<?php

namespace App\Console\Commands;

use App\Services\Prompts\PromptTemplateService;
use Illuminate\Console\Command;

class PromptCacheClearCommand extends Command
{
    protected $signature = 'prompts:cache-clear
                            {template? : Specific template path to clear}
                            {--all : Clear all prompt template caches}';

    protected $description = 'Clear cached prompt templates';

    public function handle(PromptTemplateService $service): int
    {
        $template = $this->argument('template');
        $all = $this->option('all');

        if ($template) {
            $service->clearCache($template);
            $this->info("Cleared cache for template: {$template}");
            return self::SUCCESS;
        }

        if ($all) {
            $templates = $service->listTemplates();
            foreach ($templates as $templatePath) {
                $service->clearCache($templatePath);
            }
            $this->info('Cleared all prompt template caches (' . count($templates) . ' templates)');
            return self::SUCCESS;
        }

        $service->clearCache();
        $this->info('Cleared loaded template cache');

        return self::SUCCESS;
    }
}
