<?php

namespace App\Providers;

use Jordanpartridge\SpotifyClient\SpotifyClientServiceProvider as BaseSpotifyClientServiceProvider;
use Spatie\LaravelPackageTools\Package;

/**
 * Laravel Zero compatible wrapper for SpotifyClientServiceProvider
 *
 * This provider extends the base spotify-client package provider
 * but excludes conflicting commands that have been replaced by better conduit-spotify versions
 */
class SpotifyClientServiceProvider extends BaseSpotifyClientServiceProvider
{
    /**
     * Configure the package but exclude conflicting commands
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('spotify-client')
            ->hasConfigFile();
        // Deliberately NOT registering commands to avoid conflicts:
        // ->hasCommand(SpotifyInstallCommand::class)
        // ->hasCommand(SpotifySetupCommand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Call parent boot but commands won't be registered due to configurePackage override
        parent::boot();

        // The conflicting commands are replaced by our better conduit-spotify versions:
        // - spotify:setup -> spotify:configure (beautiful Laravel Zero prompts)
        // - spotify:install -> spotify:configure (beautiful Laravel Zero prompts)
        // - spotify:play2 -> spotify:play (more features)
    }
}
