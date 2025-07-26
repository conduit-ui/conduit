<?php

namespace Conduit\Spotify;

use Conduit\Spotify\Commands\Analytics;
use Conduit\Spotify\Commands\Configure;
use Conduit\Spotify\Commands\Current;
use Conduit\Spotify\Commands\Devices;
use Conduit\Spotify\Commands\Focus;
use Conduit\Spotify\Commands\Login;
use Conduit\Spotify\Commands\Logout;
use Conduit\Spotify\Commands\Next;
use Conduit\Spotify\Commands\Pause;
use Conduit\Spotify\Commands\Play;
use Conduit\Spotify\Commands\Playlists;
use Conduit\Spotify\Commands\Queue;
use Conduit\Spotify\Commands\Search;
use Conduit\Spotify\Commands\Setup;
use Conduit\Spotify\Commands\Skip;
use Conduit\Spotify\Commands\Volume;
use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Conduit\Spotify\Services\Api;
use Conduit\Spotify\Services\Auth;
use Conduit\Spotify\Services\DeviceManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/spotify.php', 'spotify');

        // Register services
        $this->app->singleton(AuthInterface::class, Auth::class);
        $this->app->singleton(ApiInterface::class, Api::class);
        $this->app->singleton(DeviceManager::class);

        // Register commands
        $this->commands([
            Setup::class,
            Configure::class, // Backwards compatibility alias
            Login::class,
            Logout::class,
            Queue::class,
            Search::class,
            Play::class,
            Pause::class,
            Skip::class,
            Next::class, // Alias for Skip (next track only)
            Current::class,
            Volume::class,
            Playlists::class,
            Focus::class,
            Analytics::class,
            Devices::class,
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
