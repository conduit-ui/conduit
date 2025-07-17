<?php

return [
    'name' => 'Conduit',
    'version' => '1.4.0',
    'env' => 'development',
    'providers' => [
        0 => 'App\\Providers\\AppServiceProvider',
        1 => 'Illuminate\\Database\\DatabaseServiceProvider',
        2 => 'JordanPartridge\\GitHubZero\\GitHubZeroServiceProvider',
        3 => 'JordanPartridge\\ConduitSpotify\\SpotifyServiceProvider',
    ],
];
