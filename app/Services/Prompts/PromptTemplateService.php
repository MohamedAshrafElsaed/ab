<?php

namespace App\Services\Prompts;

use App\DTOs\ComposedPrompt;
use App\Enums\IntentType;
use App\Models\IntentAnalysis;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PromptTemplateService
{
    private array $config;
    private string $promptsPath;
    private array $loadedTemplates = [];

    public function __construct()
    {
        $this->config = config('prompts', []);
        $this->promptsPath = $this->config['prompts_path'] ?? resource_path('prompts');
    }

    /**
     * Load and cache a template from disk.
     */
    public function load(string $templatePath): string
    {
        $fullPath = $this->resolvePath($templatePath);
        $cacheKey = $this->getCacheKey($templatePath);

        if (isset($this->loadedTemplates[$templatePath])) {
            return $this->loadedTemplates[$templatePath];
        }

        if ($this->config['cache_templates'] ?? true) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $this->loadedTemplates[$templatePath] = $cached;
                return $cached;
            }
        }

        if (!file_exists($fullPath)) {
            Log::warning("PromptTemplate: Template not found", ['path' => $fullPath]);
            return '';
        }

        $content = file_get_contents($fullPath);
        $this->loadedTemplates[$templatePath] = $content;

        if ($this->config['cache_templates'] ?? true) {
            Cache::put($cacheKey, $content, $this->config['cache_ttl'] ?? 3600);
        }

        return $content;
    }

    /**
     * Compose a complete prompt from multiple templates.
     *
     * @param array<string> $templatePaths
     * @param array<string, mixed> $variables
     */
    public function compose(array $templatePaths, array $variables = []): string
    {
        $parts = [];

        foreach ($templatePaths as $path) {
            $template = $this->load($path);
            if (!empty($template)) {
                $parts[] = $this->render($template, $variables);
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Select appropriate templates based on intent and tech stack.
     *
     * @param array<string> $techStack
     * @return array<string>
     */
    public function selectForIntent(IntentType $intent, array $techStack): array
    {
        $templates = [];
        $intentTemplates = $this->config['intent_templates'] ?? [];
        $intentKey = $intent->value;

        if (isset($intentTemplates[$intentKey])) {
            $config = $intentTemplates[$intentKey];
            $templates[] = $config['primary'] ?? $config[0] ?? null;

            foreach ($config['includes'] ?? [] as $include) {
                $templates[] = $include;
            }
        }

        $stackTemplates = $this->config['stack_templates'] ?? [];
        foreach ($techStack as $tech) {
            $techLower = strtolower($tech);
            if (isset($stackTemplates[$techLower]['patterns'])) {
                $templates[] = $stackTemplates[$techLower]['patterns'];
            }
        }

        return array_filter($templates);
    }

    /**
     * Inject variables into template using {{VARIABLE}} syntax.
     *
     * @param array<string, mixed> $variables
     */
    public function render(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . strtoupper($key) . '}}';
            $stringValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : (string) $value;
            $template = str_replace($placeholder, $stringValue, $template);
        }

        return $template;
    }

    /**
     * Get the system prompt for a specific agent.
     */
    public function getAgentSystemPrompt(string $agentName): string
    {
        $agents = $this->config['agents'] ?? [];

        if (!isset($agents[$agentName])) {
            Log::warning("PromptTemplate: Unknown agent", ['agent' => $agentName]);
            return '';
        }

        $promptPath = $agents[$agentName]['system_prompt'] ?? null;

        if (!$promptPath) {
            return '';
        }

        return $this->load($promptPath);
    }

    /**
     * Get agent configuration.
     *
     * @return array{model?: string, max_tokens?: int, temperature?: float, thinking_budget?: int}
     */
    public function getAgentConfig(string $agentName): array
    {
        return $this->config['agents'][$agentName] ?? [];
    }

    /**
     * Build complete prompt with all context for an agent.
     *
     * @param array<string> $techStack
     * @param array<string, mixed> $context
     */
    public function buildPrompt(
        string $agentName,
        IntentType $intent,
        array $techStack,
        array $context
    ): ComposedPrompt {
        $systemPrompt = $this->getAgentSystemPrompt($agentName);
        $systemPrompt = $this->render($systemPrompt, $context);

        $taskTemplates = $this->selectForIntent($intent, $techStack);
        $userPrompt = $this->compose($taskTemplates, $context);

        return new ComposedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            metadata: [
                'agent' => $agentName,
                'intent' => $intent->value,
                'tech_stack' => $techStack,
                'templates_used' => array_merge(
                    [$this->config['agents'][$agentName]['system_prompt'] ?? ''],
                    $taskTemplates
                ),
                'built_at' => now()->toIso8601String(),
            ],
        );
    }

    /**
     * Get stack-specific template content.
     *
     * @return array<string, string>
     */
    public function getStackTemplates(string $tech, array $types = ['patterns']): array
    {
        $templates = [];
        $stackConfig = $this->config['stack_templates'][$tech] ?? [];

        foreach ($types as $type) {
            if (isset($stackConfig[$type])) {
                $templates[$type] = $this->load($stackConfig[$type]);
            }
        }

        return $templates;
    }

    /**
     * Check if a template exists.
     */
    public function exists(string $templatePath): bool
    {
        return file_exists($this->resolvePath($templatePath));
    }

    /**
     * List all available templates in a directory.
     *
     * @return array<string>
     */
    public function listTemplates(string $directory = ''): array
    {
        $path = $this->promptsPath . ($directory ? '/' . $directory : '');

        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $relativePath = str_replace($this->promptsPath . '/', '', $file->getPathname());
                $files[] = $relativePath;
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Clear template cache.
     */
    public function clearCache(?string $templatePath = null): void
    {
        if ($templatePath) {
            Cache::forget($this->getCacheKey($templatePath));
            unset($this->loadedTemplates[$templatePath]);
        } else {
            foreach (array_keys($this->loadedTemplates) as $path) {
                Cache::forget($this->getCacheKey($path));
            }
            $this->loadedTemplates = [];
        }
    }

    private function resolvePath(string $templatePath): string
    {
        if (str_starts_with($templatePath, '/')) {
            return $templatePath;
        }

        return $this->promptsPath . '/' . $templatePath;
    }

    private function getCacheKey(string $templatePath): string
    {
        $prefix = $this->config['cache_prefix'] ?? 'prompt_template:';
        return $prefix . md5($templatePath);
    }
}
