<?php

return [
    'name' => 'Conduit',
    'version' => '1.4.0',
    'env' => 'development',
    'providers' => [
        0 => 'App\\Providers\\AppServiceProvider',
        1 => 'Illuminate\\Database\\DatabaseServiceProvider',
        2 => 'JordanPartridge\\GitHubZero\\GitHubZeroServiceProvider',
        3 => 'JordanPartridge\\ConduitSpotify\\SpotifyServiceProvider', // Re-enabled for comparison
        4 => 'App\\Providers\\SpotifyClientServiceProvider', // Laravel Zero compatible wrapper
    ],
];
