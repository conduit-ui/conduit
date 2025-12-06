<?php

return [
    'default' => 'file',

    'stores' => [
        'array' => [
            'driver' => 'array',
        ],

        'file' => [
            'driver' => 'file',
            'path' => ($_SERVER['HOME'] ?? $_SERVER['USERPROFILE']).'/.conduit/cache',
        ],
    ],

    'prefix' => 'conduit_cache',
];
