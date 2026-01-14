<?php


return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('INTENT_ANALYZER_MODEL', 'claude-sonnet-4-5-20250929'),
        'max_tokens' => env('INTENT_ANALYZER_MAX_TOKENS', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Clarification Settings
    |--------------------------------------------------------------------------
    */
    'clarification_threshold' => env('INTENT_CLARIFICATION_THRESHOLD', 0.5),

    'max_clarification_questions' => 3,

    /*
    |--------------------------------------------------------------------------
    | Confidence Thresholds
    |--------------------------------------------------------------------------
    */
    'confidence' => [
        'high' => 0.8,
        'medium' => 0.5,
        'low' => 0.3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Intent Detection
    |--------------------------------------------------------------------------
    */
    'multi_intent' => [
        'enabled' => true,
        'suggest_split' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('INTENT_ANALYZER_LOGGING', true),
        'log_raw_responses' => env('INTENT_ANALYZER_LOG_RAW', false),
    ],
];
