<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Template Caching
    |--------------------------------------------------------------------------
    */
    'cache_templates' => env('CACHE_PROMPT_TEMPLATES', true),
    'cache_ttl' => env('PROMPT_CACHE_TTL', 3600),
    'cache_prefix' => 'prompt_template:',

    /*
    |--------------------------------------------------------------------------
    | Prompt Directory
    |--------------------------------------------------------------------------
    */
    'prompts_path' => resource_path('prompts'),

    /*
    |--------------------------------------------------------------------------
    | Agent Configurations
    |--------------------------------------------------------------------------
    */
    'agents' => [
        'orchestrator' => [
            'system_prompt' => 'system/orchestrator.md',
            'model' => env('ORCHESTRATOR_MODEL', 'claude-sonnet-4-5-20250514'),
            'max_tokens' => env('ORCHESTRATOR_MAX_TOKENS', 4096),
            'temperature' => 0.3,
            'description' => 'Main coordinator agent that routes requests and manages workflow',
        ],
        'planner' => [
            'system_prompt' => 'system/code_planner.md',
            'model' => env('PLANNING_MODEL', 'claude-sonnet-4-5-20250514'),
            'max_tokens' => 8192,
            'temperature' => 0.4,
            'thinking_budget' => 16000,
            'description' => 'Creates detailed implementation plans',
        ],
        'executor' => [
            'system_prompt' => 'system/code_executor.md',
            'model' => env('EXECUTION_MODEL', 'claude-sonnet-4-5-20250514'),
            'max_tokens' => 8192,
            'temperature' => 0.2,
            'description' => 'Generates precise code changes',
        ],
        'intent_analyzer' => [
            'system_prompt' => 'system/intent_analyzer.md',
            'model' => env('INTENT_ANALYZER_MODEL', 'claude-sonnet-4-5-20250929'),
            'max_tokens' => 1024,
            'temperature' => 0.1,
            'description' => 'Classifies user intent and extracts entities',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Intent to Task Template Mapping
    |--------------------------------------------------------------------------
    */
    'intent_templates' => [
        'feature_request' => [
            'primary' => 'tasks/feature_request.md',
            'includes' => ['partials/output_format.md', 'partials/file_change_format.md'],
        ],
        'bug_fix' => [
            'primary' => 'tasks/bug_fix.md',
            'includes' => ['partials/output_format.md', 'partials/error_handling.md'],
        ],
        'test_writing' => [
            'primary' => 'tasks/test_writing.md',
            'includes' => ['partials/output_format.md'],
        ],
        'ui_component' => [
            'primary' => 'tasks/ui_component.md',
            'includes' => ['partials/output_format.md', 'partials/file_change_format.md'],
        ],
        'refactoring' => [
            'primary' => 'tasks/refactoring.md',
            'includes' => ['partials/output_format.md', 'partials/file_change_format.md'],
        ],
        'question' => [
            'primary' => 'tasks/question.md',
            'includes' => ['partials/output_format.md'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stack-Specific Templates
    |--------------------------------------------------------------------------
    */
    'stack_templates' => [
        'laravel' => [
            'patterns' => 'stack/laravel/patterns.md',
            'eloquent' => 'stack/laravel/eloquent.md',
            'controllers' => 'stack/laravel/controllers.md',
            'migrations' => 'stack/laravel/migrations.md',
        ],
        'vue' => [
            'patterns' => 'stack/vue/patterns.md',
            'components' => 'stack/vue/components.md',
            'composables' => 'stack/vue/composables.md',
        ],
        'inertia' => [
            'patterns' => 'stack/inertia/patterns.md',
        ],
        'livewire' => [
            'patterns' => 'stack/livewire/patterns.md',
        ],
        'react' => [
            'patterns' => 'stack/react/patterns.md',
        ],
        'tailwind' => [
            'patterns' => 'stack/tailwind/patterns.md',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Variables
    |--------------------------------------------------------------------------
    */
    'variables' => [
        'max_context_tokens' => 60000,
        'max_code_preview_lines' => 100,
        'include_line_numbers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Estimation
    |--------------------------------------------------------------------------
    */
    'token_estimation' => [
        'chars_per_token' => 4,
        'safety_margin' => 0.1,
    ],
];
