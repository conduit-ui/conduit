<?php

return [
    'name' => 'Conduit',
    'version' => '2.4.1',
    'env' => 'development',
    'providers' => [
        0 => 'App\\Providers\\AppServiceProvider',
        1 => 'Illuminate\\Database\\DatabaseServiceProvider',
        2 => 'JordanPartridge\\GitHubZero\\GitHubZeroServiceProvider',
        3 => 'Conduit\\Spotify\\ServiceProvider', // Re-enabled for comparison
        4 => 'App\\Providers\\SpotifyClientServiceProvider', // Laravel Zero compatible wrapper
    ],
];
