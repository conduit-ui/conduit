<?php

return [
    'name' => 'Conduit',
    'version' => app('git.version'),
    'env' => 'development',
    'providers' => [
        0 => 'App\\Providers\\AppServiceProvider',
        1 => 'Illuminate\\Database\\DatabaseServiceProvider',
    ],
];
