<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */
    'provider' => env('ASKAI_PROVIDER', 'anthropic'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('ASKAI_MODEL', 'gpt-4-turbo-preview'),
        'max_tokens' => env('ASKAI_MAX_TOKENS', 4096),
        'temperature' => env('ASKAI_TEMPERATURE', 0.1),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ASKAI_ANTHROPIC_MODEL', 'claude-opus-4-5-20251101'),
        'max_tokens' => env('ASKAI_MAX_TOKENS', 4096),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval Configuration
    |--------------------------------------------------------------------------
    */
    'retrieval' => [
        // Maximum chunks to retrieve per query
        'max_chunks' => env('ASKAI_MAX_CHUNKS', 1000),

        // Maximum total content length in characters
        'max_content_length' => env('ASKAI_MAX_CONTENT_LENGTH', 80000),

        // Minimum chunks to ensure diversity
        'min_diverse_files' => 3,

        // Boost factors for relevance scoring
        'boost' => [
            'exact_path_match' => 10.0,
            'path_contains' => 5.0,
            'symbol_match' => 8.0,
            'import_match' => 6.0,
            'route_match' => 7.0,
            'content_keyword' => 3.0,
            'framework_hint' => 2.0,
        ],

        // Stack-aware path priorities (relative boost)
        'stack_paths' => [
            'laravel' => ['app/', 'routes/', 'config/', 'database/migrations/'],
            'vue' => ['resources/js/', 'resources/views/'],
            'inertia' => ['resources/js/pages/', 'resources/js/Pages/'],
            'livewire' => ['app/Livewire/', 'resources/views/livewire/'],
            'react' => ['resources/js/', 'src/'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Configuration
    |--------------------------------------------------------------------------
    */
    'response' => [
        // Confidence thresholds
        'confidence' => [
            'high' => 0.8,
            'medium' => 0.5,
        ],

        // Maximum snippets per audit log entry
        'max_snippets_per_citation' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('ASKAI_CACHE_ENABLED', true),
        'ttl' => env('ASKAI_CACHE_TTL', 300), // 5 minutes
        'prefix' => 'askai_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security - Sensitive Content Patterns
    |--------------------------------------------------------------------------
    */
    'redaction' => [
        'enabled' => true,
        'patterns' => [
            // Environment variables with secrets
            '/(?:API_KEY|SECRET|PASSWORD|TOKEN|PRIVATE_KEY|AUTH_KEY|DB_PASSWORD|MAIL_PASSWORD|AWS_SECRET)\s*=\s*[\'"]?([^\s\'"]+)[\'"]?/i',
            // Inline secrets in code
            '/(?:api[_-]?key|secret|password|token|auth[_-]?key)\s*[:=]\s*[\'"]([^\'"]{8,})[\'"]?/i',
            // Bearer tokens
            '/Bearer\s+[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+/i',
            // AWS keys
            '/(?:AKIA|ABIA|ACCA|ASIA)[A-Z0-9]{16}/i',
            // Private keys
            '/-----BEGIN\s+(?:RSA\s+)?PRIVATE\s+KEY-----/i',
            // Database connection strings with passwords
            '/(?:mysql|postgres|mongodb):\/\/[^:]+:([^@]+)@/i',
        ],
        'replacement' => '[REDACTED]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'enabled' => env('ASKAI_RATE_LIMIT_ENABLED', true),
        'max_requests_per_minute' => env('ASKAI_RATE_LIMIT', 10),
    ],
];
