<?php

return [
    'default' => 'file',

    'stores' => [
        'array' => [
            'driver' => 'array',
        ],

        'file' => [
            'driver' => 'file',
            'path' => $_SERVER['HOME'].'/.conduit/cache',
        ],
    ],

    'prefix' => 'conduit_cache',
];
