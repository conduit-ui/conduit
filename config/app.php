<?php

return [
    'name' => 'Conduit',
    'version' => 'unreleased',
    'env' => 'development',
    'providers' => [
        'App\Providers\AppServiceProvider',
        'Illuminate\Database\DatabaseServiceProvider',
        'JordanPartridge\GitHubZero\GitHubZeroServiceProvider',
        'JordanPartridge\LaravelSayLogger\LaravelSayLoggerServiceProvider',
    ],
];
