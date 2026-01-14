<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claude API Configuration
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('PLANNING_MODEL', 'claude-sonnet-4-5-20250514'),
        'max_tokens' => env('PLANNING_MAX_TOKENS', 8192),
        'thinking_budget' => env('PLANNING_THINKING_BUDGET', 16000),
        'timeout' => env('PLANNING_TIMEOUT', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Retrieval Settings
    |--------------------------------------------------------------------------
    */
    'context' => [
        'max_chunks' => env('PLANNING_MAX_CHUNKS', 60),
        'token_budget' => env('PLANNING_TOKEN_BUDGET', 80000),
        'include_dependencies' => true,
        'dependency_depth' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Validation Settings
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'require_content_for_create' => true,
        'require_changes_for_modify' => true,
        'check_file_existence' => true,
        'detect_circular_dependencies' => true,
        'max_files_per_plan' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Assessment Thresholds
    |--------------------------------------------------------------------------
    */
    'risk' => [
        'high_delete_threshold' => 3,
        'high_modify_threshold' => 10,
        'allow_auto_execution' => [
            'max_risk_level' => 'low',
            'max_files' => 3,
            'require_no_deletes' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'keep_completed_days' => 30,
        'keep_failed_days' => 7,
        'keep_rejected_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('PLANNING_LOGGING', true),
        'log_prompts' => env('PLANNING_LOG_PROMPTS', false),
        'log_responses' => env('PLANNING_LOG_RESPONSES', false),
    ],
];
