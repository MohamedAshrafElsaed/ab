<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claude API Configuration
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ORCHESTRATOR_MODEL', 'claude-sonnet-4-5-20250514'),
        'max_tokens' => env('ORCHESTRATOR_MAX_TOKENS', 4096),
        'temperature' => env('ORCHESTRATOR_TEMPERATURE', 0.3),
        'timeout' => env('ORCHESTRATOR_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Retrieval Settings
    |--------------------------------------------------------------------------
    */
    'context' => [
        'max_chunks' => env('ORCHESTRATOR_MAX_CHUNKS', 60),
        'token_budget' => env('ORCHESTRATOR_TOKEN_BUDGET', 80000),
        'include_dependencies' => true,
        'default_depth' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Settings
    |--------------------------------------------------------------------------
    */
    'execution' => [
        'auto_approve' => env('ORCHESTRATOR_AUTO_APPROVE', false),
        'stop_on_error' => env('ORCHESTRATOR_STOP_ON_ERROR', true),
        'create_backups' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Intent Analysis Settings
    |--------------------------------------------------------------------------
    */
    'intent' => [
        'clarification_threshold' => env('INTENT_CLARIFICATION_THRESHOLD', 0.5),
        'max_clarification_questions' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Settings
    |--------------------------------------------------------------------------
    */
    'conversation' => [
        'max_history_messages' => 20,
        'title_max_length' => 50,
        'auto_generate_title' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase Timeouts (seconds)
    |--------------------------------------------------------------------------
    */
    'timeouts' => [
        'intent_analysis' => 30,
        'context_retrieval' => 60,
        'planning' => 120,
        'execution_per_file' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('ORCHESTRATOR_LOGGING', true),
        'log_events' => env('ORCHESTRATOR_LOG_EVENTS', true),
        'log_api_calls' => env('ORCHESTRATOR_LOG_API', false),
    ],
];
