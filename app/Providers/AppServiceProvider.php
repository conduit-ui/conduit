<?php

namespace App\Providers;

use App\Contracts\ComponentManagerInterface;
use App\Contracts\ComponentStorageInterface;
use App\Contracts\PackageInstallerInterface;
use App\Services\ComponentInstallationService;
use App\Services\ComponentManager;
use App\Services\ComponentStorage;
use App\Services\GithubAuthService;
use App\Services\SecurePackageInstaller;
use App\Services\ServiceProviderDetector;
use Illuminate\Support\ServiceProvider;
use JordanPartridge\GithubClient\Contracts\GithubConnectorInterface;
use JordanPartridge\GithubClient\GithubConnector;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register GitHub auth service with fallback FIRST
        $this->app->singleton(GithubAuthService::class);

        // Set a default GitHub token to prevent errors during service provider registration
        if (empty(config('github-client.token'))) {
            $authService = new GithubAuthService;
            $token = $authService->getToken();

            // Set the token in config so other providers don't throw errors
            config(['github-client.token' => $token ?: 'dummy_token_for_startup']);
        }

        // Override GitHub client with optional auth after registration
        $this->app->extend(GithubConnectorInterface::class, function ($connector, $app) {
            $authService = $app->make(GithubAuthService::class);
            $token = $authService->getToken();

            // Return new connector with actual token or null
            return new GithubConnector($token);
        });

        // Bind interfaces to implementations
        $this->app->singleton(ComponentStorageInterface::class, ComponentStorage::class);
        $this->app->singleton(ComponentManagerInterface::class, ComponentManager::class);
        $this->app->singleton(PackageInstallerInterface::class, SecurePackageInstaller::class);

        // Register concrete services
        $this->app->singleton(ComponentStorage::class);
        $this->app->singleton(ComponentManager::class);
        $this->app->singleton(SecurePackageInstaller::class);
        $this->app->singleton(ServiceProviderDetector::class);
        $this->app->singleton(ComponentInstallationService::class);
    }
}
