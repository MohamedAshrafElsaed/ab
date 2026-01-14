<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME', env('APP_NAME', 'AIBuilder')),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'
    ),

    'middleware' => ['web', 'auth'],

    'waits' => [
        'redis:default' => 60,
        'redis:scans' => 120,
        'redis:webhooks' => 30,
    ],

    'trim' => [
        'recent' => 1440,        // 24 hours
        'pending' => 1440,
        'completed' => 1440,
        'recent_failed' => 10080, // 7 days
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        // Silence noisy jobs if needed
    ],

    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 72,   // 3 days of snapshots
            'queue' => 72,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Supervisors:
    | - scans: Heavy scanning jobs (clone, manifest, chunks, kb build)
    | - webhooks: Fast webhook-triggered updates
    | - default: General purpose jobs
    |
    */

    'defaults' => [
        'supervisor-scans' => [
            'connection' => 'redis',
            'queue' => ['scans'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 3,
            'timeout' => 600,
            'nice' => 0,
        ],
        'supervisor-webhooks' => [
            'connection' => 'redis',
            'queue' => ['webhooks'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 300,
            'nice' => 0,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-scans' => [
                'minProcesses' => 2,
                'maxProcesses' => 8,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'supervisor-webhooks' => [
                'minProcesses' => 1,
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-default' => [
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-scans' => [
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-webhooks' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-default' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],
    ],
];
