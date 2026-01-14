<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Project Storage Path
    |--------------------------------------------------------------------------
    |
    | The base path where project repositories and knowledge data are stored.
    |
    */
    'storage_path' => storage_path('app/projects'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size in bytes to process. Files larger than this are skipped.
    |
    */
    'max_file_size' => env('PROJECT_MAX_FILE_SIZE', 1024 * 1024), // 1MB

    /*
    |--------------------------------------------------------------------------
    | Chunking Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for splitting large files into chunks for retrieval.
    |
    | Chunk Size Strategy:
    | - Target range: 250-400 lines per chunk (configurable)
    | - Files under max_lines are kept as single chunks
    | - Larger files are split at logical boundaries (functions, classes)
    |
    */
    'chunking' => [
        // Maximum bytes per chunk (soft limit, respected when possible)
        'max_bytes' => env('CHUNK_MAX_BYTES', 200 * 1024), // 200KB

        // Maximum lines per chunk - upper bound
        'max_lines' => env('CHUNK_MAX_LINES', 400),

        // Minimum lines per chunk - lower bound (prevents tiny chunks)
        'min_lines' => env('CHUNK_MIN_LINES', 250),

        // Weights for determining break points when splitting
        'break_weights' => [
            'empty_line' => 10,        // Prefer breaking at empty lines
            'function_boundary' => 8,  // Function/method declarations
            'class_boundary' => 9,     // Class/trait/interface declarations
            'block_end' => 7,          // Closing braces
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipeline Stages
    |--------------------------------------------------------------------------
    |
    | Configuration for the scanning pipeline stages.
    | Weight determines the relative portion of total progress.
    |
    */
    'pipeline_stages' => [
        'workspace' => [
            'name' => 'Preparing workspace',
            'weight' => 5,
        ],
        'clone' => [
            'name' => 'Cloning repository',
            'weight' => 20,
        ],
        'manifest' => [
            'name' => 'Building file manifest',
            'weight' => 25,
        ],
        'stack' => [
            'name' => 'Detecting stack',
            'weight' => 5,
        ],
        'chunks' => [
            'name' => 'Creating knowledge chunks',
            'weight' => 35,
        ],
        'finalize' => [
            'name' => 'Finalizing',
            'weight' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusion Rules
    |--------------------------------------------------------------------------
    |
    | Default rules for excluding files and directories from scanning.
    |
    */
    'exclusions' => [
        // Toggle switches for common inclusions
        'toggles' => [
            'include_vendor' => env('SCAN_INCLUDE_VENDOR', false),
            'include_node_modules' => env('SCAN_INCLUDE_NODE_MODULES', false),
            'include_storage' => env('SCAN_INCLUDE_STORAGE', false),
            'include_lock_files' => env('SCAN_INCLUDE_LOCK_FILES', false),
            'include_source_maps' => env('SCAN_INCLUDE_SOURCE_MAPS', false),
            'include_minified' => env('SCAN_INCLUDE_MINIFIED', false),
        ],

        // Allow project-level overrides via project_scan_config.json
        'allow_project_overrides' => true,

        // Directories to exclude (exact match against path segments)
        'directories' => [
            '.git',
            '.svn',
            '.hg',
            'vendor',
            'node_modules',
            'bower_components',
            'storage',
            'bootstrap/cache',
            'public/build',
            'public/hot',
            'dist',
            'build',
            '.output',
            '.next',
            '.nuxt',
            '.idea',
            '.vscode',
            '.fleet',
            'cache',
            '.cache',
            '__pycache__',
            '.pytest_cache',
            '.mypy_cache',
            '.phpunit.cache',
            'coverage',
            '.nyc_output',
        ],

        // Glob patterns to exclude
        'patterns' => [
            '**/node_modules/**',
            '**/vendor/**',
            '**/.git/**',
            '**/storage/logs/**',
            '**/storage/framework/**',
            '**/bootstrap/cache/**',
        ],

        // Specific files to exclude
        'files' => [
            '.DS_Store',
            'Thumbs.db',
            '.gitkeep',
            '.gitignore',
            '.editorconfig',
        ],

        // Extensions to exclude
        'extensions' => [
            'lock',
            'log',
            'map',
            'min.js',
            'min.css',
            'bundle.js',
            'chunk.js',
        ],

        // Binary file extensions (not scanned for content)
        'binary_extensions' => [
            // Images
            'png', 'jpg', 'jpeg', 'gif', 'bmp', 'ico', 'webp', 'svg', 'avif', 'tiff',
            // Audio/Video
            'mp3', 'mp4', 'wav', 'avi', 'mov', 'mkv', 'webm', 'ogg', 'flac',
            // Documents
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            // Archives
            'zip', 'tar', 'gz', 'rar', '7z', 'bz2', 'xz',
            // Executables
            'exe', 'dll', 'so', 'dylib', 'bin', 'app',
            // Fonts
            'ttf', 'otf', 'woff', 'woff2', 'eot',
            // Databases
            'sqlite', 'db', 'sqlite3', 'mdb',
            // Other
            'phar', 'jar', 'war',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Framework Hints
    |--------------------------------------------------------------------------
    |
    | Patterns for detecting framework-specific files.
    |
    */
    'framework_hints' => [
        'path_patterns' => [
            'laravel' => [
                'app/Http/Controllers/**',
                'app/Models/**',
                'routes/*.php',
            ],
            'eloquent' => [
                'app/Models/**',
            ],
            'blade' => [
                'resources/views/**/*.blade.php',
            ],
            'livewire' => [
                'app/Livewire/**',
                'app/Http/Livewire/**',
                'resources/views/livewire/**',
            ],
            'inertia' => [
                'resources/js/Pages/**',
                'resources/js/pages/**',
            ],
        ],
        'content_markers' => [
            'eloquent' => [
                'extends Model',
                'use HasFactory',
            ],
            'livewire' => [
                'extends Component',
                'use Livewire',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Language Detection
    |--------------------------------------------------------------------------
    |
    | Mapping of file extensions to language identifiers.
    |
    */
    'languages' => [
        'extension_map' => [
            'php' => 'php',
            'blade.php' => 'blade',
            'js' => 'javascript',
            'mjs' => 'javascript',
            'cjs' => 'javascript',
            'ts' => 'typescript',
            'mts' => 'typescript',
            'tsx' => 'typescriptreact',
            'jsx' => 'javascriptreact',
            'vue' => 'vue',
            'svelte' => 'svelte',
            'css' => 'css',
            'scss' => 'scss',
            'sass' => 'sass',
            'less' => 'less',
            'json' => 'json',
            'yml' => 'yaml',
            'yaml' => 'yaml',
            'md' => 'markdown',
            'mdx' => 'mdx',
            'sql' => 'sql',
            'sh' => 'shell',
            'bash' => 'shell',
            'zsh' => 'shell',
            'xml' => 'xml',
            'html' => 'html',
            'twig' => 'twig',
            'env' => 'dotenv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge Base Output
    |--------------------------------------------------------------------------
    |
    | Configuration for the standardized KB output format.
    |
    */
    'knowledge_base' => [
        // Number of old scans to keep per project
        'keep_old_scans' => env('KB_KEEP_OLD_SCANS', 3),

        // Use NDJSON for files_index when file count exceeds this
        'ndjson_threshold' => 10000,

        // Scanner version identifier
        'version' => '2.1.0',
    ],
];
