<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claude API Configuration
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('EXECUTION_MODEL', 'claude-sonnet-4-5-20250514'),
        'max_tokens' => env('EXECUTION_MAX_TOKENS', 8192),
        'timeout' => env('EXECUTION_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'auto_approve' => env('EXECUTION_AUTO_APPROVE', false),
        'stop_on_error' => env('EXECUTION_STOP_ON_ERROR', true),
        'create_backups' => env('EXECUTION_CREATE_BACKUPS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'enabled' => env('EXECUTION_BACKUP_ENABLED', true),
        'path' => storage_path('app/backups'),
        'retention_days' => env('EXECUTION_BACKUP_RETENTION_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Diff Configuration
    |--------------------------------------------------------------------------
    */
    'diff' => [
        'context_lines' => 3,
        'max_diff_size' => 100000,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Size Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_file_size' => env('EXECUTION_MAX_FILE_SIZE', 1024 * 1024), // 1MB
        'max_files_per_plan' => env('EXECUTION_MAX_FILES', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('EXECUTION_LOGGING', true),
        'log_content' => env('EXECUTION_LOG_CONTENT', false),
    ],
];
