<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Spotify API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Spotify application credentials. Create an app at:
    | https://developer.spotify.com/dashboard/applications
    |
    */

    'client_id' => env('SPOTIFY_CLIENT_ID'),
    'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
    'redirect_uri' => env('SPOTIFY_REDIRECT_URI', 'http://localhost:8888/callback'),

    /*
    |--------------------------------------------------------------------------
    | Default Scopes
    |--------------------------------------------------------------------------
    |
    | The default scopes to request when authenticating with Spotify.
    |
    */

    'scopes' => [
        'user-read-playback-state',
        'user-modify-playback-state',
        'user-read-currently-playing',
        'playlist-read-private',
        'playlist-modify-public',
        'playlist-modify-private',
        'user-library-read',
        'user-library-modify',
    ],

    /*
    |--------------------------------------------------------------------------
    | Preset Playlists
    |--------------------------------------------------------------------------
    |
    | Predefined playlists for different coding scenarios.
    | Use Spotify URIs or playlist IDs.
    |
    */

    'presets' => [
        'coding' => env('SPOTIFY_CODING_PLAYLIST', 'spotify:playlist:37i9dQZF1DX0XUsuxWHRQd'), // Deep Focus
        'break' => env('SPOTIFY_BREAK_PLAYLIST', 'spotify:playlist:37i9dQZF1DX3rxVfibe1L0'), // Chill Hits
        'deploy' => env('SPOTIFY_DEPLOY_PLAYLIST', 'spotify:playlist:37i9dQZF1DX0XUfTFmNBRM'), // Upbeat Indie
        'debug' => env('SPOTIFY_DEBUG_PLAYLIST', 'spotify:playlist:37i9dQZF1DX4sWSpwAYIy1'), // Peaceful Piano
        'testing' => env('SPOTIFY_TESTING_PLAYLIST', 'spotify:playlist:37i9dQZF1DX5trt9i14X7j'), // Concentration
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Activation
    |--------------------------------------------------------------------------
    |
    | Define when the Spotify component should be active.
    |
    */

    'activation' => [
        'events' => [
            'coding.start',
            'git.working', 
            'time.work_hours',
            'project.laravel',
            'project.php',
        ],
        'exclude_events' => [
            'meeting.active',
            'call.active',
        ],
        'always_active' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-play Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic music behavior.
    |
    */

    'auto_play' => [
        'on_coding_start' => env('SPOTIFY_AUTO_PLAY_CODING', false),
        'on_break' => env('SPOTIFY_AUTO_PLAY_BREAK', false),
        'on_deploy_success' => env('SPOTIFY_AUTO_PLAY_DEPLOY', true),
        'volume' => env('SPOTIFY_DEFAULT_VOLUME', 70),
    ],
];