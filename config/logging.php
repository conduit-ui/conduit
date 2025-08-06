<?php

return [
    'default' => env('LOG_CHANNEL', 'single'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => env('CONDUIT_STORAGE_PATH', storage_path()).'/logs/conduit.log',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => env('CONDUIT_STORAGE_PATH', storage_path()).'/logs/conduit.log',
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => env('CONDUIT_STORAGE_PATH', storage_path()).'/logs/conduit.log',
        ],

        'deprecations' => [
            'driver' => 'single',
            'path' => env('CONDUIT_STORAGE_PATH', storage_path()).'/logs/deprecations.log',
            'level' => env('LOG_LEVEL', 'info'),
            'replace_placeholders' => true,
        ],
    ],
];