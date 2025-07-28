<?php

return [
    'name' => 'Conduit',
    'version' => '2.8.0',
    'env' => 'development',
    'providers' => [
        0 => 'App\\Providers\\AppServiceProvider',
        1 => 'Illuminate\\Database\\DatabaseServiceProvider',
        2 => 'Conduit\\Spotify\\ServiceProvider',
        3 => 'App\\Providers\\SpotifyClientServiceProvider',
        4 => 'Jordanpartridge\\ConduitEnvmanager\\ServiceProvider',
        5 => 'JordanPartridge\\ConduitSpotify\\ServiceProvider',
    ],
];
