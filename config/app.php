<?php

return [
    'name' => 'Conduit',
    'version' => '1.3.2',
    'env' => 'development',
    'providers' => [
        0 => 'App\\Providers\\AppServiceProvider',
        1 => 'Illuminate\\Database\\DatabaseServiceProvider',
        2 => 'JordanPartridge\\GitHubZero\\GitHubZeroServiceProvider',
        3 => 'JordanPartridge\\ConduitSpotify\\SpotifyServiceProvider',
    ],
];
