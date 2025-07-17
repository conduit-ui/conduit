<?php

namespace JordanPartridge\ConduitSpotify;

use Illuminate\Support\ServiceProvider;
use JordanPartridge\ConduitSpotify\Commands\SpotifyAuthCommand;
use JordanPartridge\ConduitSpotify\Commands\SpotifyCurrentCommand;
use JordanPartridge\ConduitSpotify\Commands\SpotifyFocusCommand;
use JordanPartridge\ConduitSpotify\Commands\SpotifyPauseCommand;
use JordanPartridge\ConduitSpotify\Commands\SpotifyPlayCommand;
use JordanPartridge\ConduitSpotify\Commands\SpotifyPlaylistsCommand;
use JordanPartridge\ConduitSpotify\Commands\SpotifySetupCommand;
use JordanPartridge\ConduitSpotify\Commands\SpotifySkipCommand;
use JordanPartridge\ConduitSpotify\Commands\SpotifyVolumeCommand;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;
use JordanPartridge\ConduitSpotify\Services\SpotifyApiService;
use JordanPartridge\ConduitSpotify\Services\SpotifyAuthService;

class SpotifyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/spotify.php', 'spotify');

        // Register services
        $this->app->singleton(SpotifyAuthInterface::class, SpotifyAuthService::class);
        $this->app->singleton(SpotifyApiInterface::class, SpotifyApiService::class);

        // Register commands
        $this->commands([
            SpotifySetupCommand::class,
            SpotifyAuthCommand::class,
            SpotifyPlayCommand::class,
            SpotifyPauseCommand::class,
            SpotifySkipCommand::class,
            SpotifyCurrentCommand::class,
            SpotifyVolumeCommand::class,
            SpotifyPlaylistsCommand::class,
            SpotifyFocusCommand::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/spotify.php' => config_path('spotify.php'),
            ], 'spotify-config');
        }
    }
}
