<?php

return [
    'name' => 'Conduit',
    'version' => function_exists('base_path') && is_dir(base_path('.git'))
        ? trim(shell_exec('git describe --tags --abbrev=0 2>/dev/null || echo "1.9.0"'))
        : '1.9.0',
    'env' => 'development',
    'providers' => [
        0 => 'App\\Providers\\AppServiceProvider',
        1 => 'Illuminate\\Database\\DatabaseServiceProvider',
        2 => 'JordanPartridge\\GitHubZero\\GitHubZeroServiceProvider',
        3 => 'Conduit\\Spotify\\ServiceProvider', // Re-enabled for comparison
        4 => 'App\\Providers\\SpotifyClientServiceProvider', // Laravel Zero compatible wrapper
    ],
];
