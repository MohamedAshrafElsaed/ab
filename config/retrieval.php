<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Retrieval Configuration
    |--------------------------------------------------------------------------
    */

    // Maximum chunks to return per retrieval
    'max_chunks' => env('RETRIEVAL_MAX_CHUNKS', 50),

    // Maximum total token budget for context
    'max_token_budget' => env('RETRIEVAL_MAX_TOKEN_BUDGET', 100000),

    // Estimated tokens per character (for estimation)
    'tokens_per_char' => 0.25,

    // Default dependency expansion depth
    'default_dependency_depth' => 2,

    // Maximum dependency expansion depth
    'max_dependency_depth' => 5,

    /*
    |--------------------------------------------------------------------------
    | Relevance Scoring Weights
    |--------------------------------------------------------------------------
    */
    'scoring' => [
        'weights' => [
            'keyword_match' => 0.25,
            'file_type_relevance' => 0.20,
            'domain_match' => 0.20,
            'dependency_proximity' => 0.15,
            'route_relevance' => 0.10,
            'symbol_match' => 0.10,
        ],

        'boost' => [
            'exact_path_match' => 10.0,
            'path_contains' => 5.0,
            'symbol_declared' => 8.0,
            'symbol_used' => 4.0,
            'import_match' => 6.0,
            'route_handler' => 9.0,
            'route_related' => 5.0,
            'content_keyword' => 3.0,
            'framework_path' => 2.0,
            'entry_point' => 7.0,
            'direct_dependency' => 6.0,
            'indirect_dependency' => 3.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Intent-Based File Type Mapping
    |--------------------------------------------------------------------------
    */
    'intent_file_types' => [
        'feature_request' => [
            'primary' => ['Controller', 'Service', 'Action'],
            'secondary' => ['Model', 'Request', 'Resource', 'View', 'Component'],
        ],
        'bug_fix' => [
            'primary' => ['Controller', 'Service', 'Model'],
            'secondary' => ['Middleware', 'Exception', 'Test'],
        ],
        'test_writing' => [
            'primary' => ['Test', 'Service', 'Controller'],
            'secondary' => ['Factory', 'Model'],
        ],
        'ui_component' => [
            'primary' => ['Component', 'View', 'Page'],
            'secondary' => ['Controller', 'Resource', 'css', 'scss'],
        ],
        'refactoring' => [
            'primary' => ['Service', 'Action', 'Controller'],
            'secondary' => ['Model', 'Repository', 'Interface'],
        ],
        'question' => [
            'primary' => [],
            'secondary' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Path Mappings
    |--------------------------------------------------------------------------
    */
    'domain_paths' => [
        'auth' => [
            'app/Http/Controllers/Auth',
            'app/Http/Middleware',
            'app/Guards',
            'app/Policies',
            'routes/auth.php',
            'resources/js/pages/auth',
            'resources/views/auth',
        ],
        'users' => [
            'app/Models/User.php',
            'app/Http/Controllers/User',
            'app/Services/User',
            'database/migrations/*_users_*',
        ],
        'api' => [
            'app/Http/Controllers/Api',
            'routes/api.php',
            'app/Http/Resources',
        ],
        'database' => [
            'app/Models',
            'database/migrations',
            'database/seeders',
            'database/factories',
        ],
        'ui' => [
            'resources/js/components',
            'resources/js/pages',
            'resources/js/Pages',
            'resources/views',
            'resources/css',
        ],
        'testing' => [
            'tests/Feature',
            'tests/Unit',
            'tests/Browser',
        ],
        'config' => [
            'config/',
            '.env.example',
        ],
        'services' => [
            'app/Services',
            'app/Actions',
            'app/Jobs',
        ],
        'events' => [
            'app/Events',
            'app/Listeners',
            'app/Notifications',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stack-Aware Path Priorities
    |--------------------------------------------------------------------------
    */
    'stack_paths' => [
        'laravel' => [
            'app/Http/Controllers',
            'app/Models',
            'app/Services',
            'routes',
            'config',
            'database/migrations',
        ],
        'vue' => [
            'resources/js/components',
            'resources/js/pages',
            'resources/js/composables',
        ],
        'inertia' => [
            'resources/js/Pages',
            'resources/js/Layouts',
            'app/Http/Controllers',
        ],
        'livewire' => [
            'app/Livewire',
            'resources/views/livewire',
        ],
        'react' => [
            'resources/js/components',
            'resources/js/pages',
            'resources/js/hooks',
        ],
        'tailwind' => [
            'tailwind.config.js',
            'resources/css',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbol Graph Configuration
    |--------------------------------------------------------------------------
    */
    'symbol_graph' => [
        // Cache TTL in seconds (1 hour)
        'cache_ttl' => 3600,

        // Maximum nodes to process
        'max_nodes' => 5000,

        // Relationship types
        'relationship_types' => [
            'imports' => 1.0,
            'extends' => 0.9,
            'implements' => 0.9,
            'uses_trait' => 0.8,
            'instantiates' => 0.7,
            'calls' => 0.6,
            'references' => 0.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Analyzer Configuration
    |--------------------------------------------------------------------------
    */
    'route_analyzer' => [
        // Cache TTL in seconds (30 minutes)
        'cache_ttl' => 1800,

        // File patterns for route-related files
        'handler_patterns' => [
            'controller' => 'app/Http/Controllers/%s.php',
            'request' => 'app/Http/Requests/%sRequest.php',
            'resource' => 'app/Http/Resources/%sResource.php',
            'model' => 'app/Models/%s.php',
            'view' => 'resources/views/%s.blade.php',
            'page' => 'resources/js/Pages/%s.vue',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('RETRIEVAL_CACHE_ENABLED', true),
        'prefix' => 'retrieval',
        'ttl' => [
            'symbol_graph' => 3600,  // 1 hour
            'routes' => 1800,        // 30 minutes
            'retrieval_result' => 300, // 5 minutes
        ],
    ],
];
