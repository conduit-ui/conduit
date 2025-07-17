<?php

namespace App\Providers;

use Jordanpartridge\SpotifyClient\Commands\SpotifyInstallCommand;
use Jordanpartridge\SpotifyClient\Commands\SpotifySetupCommand;
use Jordanpartridge\SpotifyClient\SpotifyClientServiceProvider as BaseSpotifyClientServiceProvider;

/**
 * Laravel Zero compatible wrapper for SpotifyClientServiceProvider
 *
 * This provider extends the base spotify-client package provider
 * and adds Laravel Zero specific command registration
 */
class SpotifyClientServiceProvider extends BaseSpotifyClientServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Register commands for Laravel Zero
        if ($this->app->runningInConsole()) {
            $this->commands([
                SpotifyInstallCommand::class,
                SpotifySetupCommand::class,
            ]);
        }
    }
}
