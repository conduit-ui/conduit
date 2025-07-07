<?php

namespace App\Providers;

use App\Contracts\ComponentManagerInterface;
use App\Contracts\ComponentPersistenceInterface;
use App\Contracts\ComponentStorageInterface;
use App\Contracts\PackageInstallerInterface;
use App\Services\ComponentDiscoveryService;
use App\Services\ComponentInstallationService;
use App\Services\ComponentManager;
use App\Services\ComponentPersistence;
use App\Services\ComponentStorage;
use App\Services\SecurePackageInstaller;
use App\Services\ServiceProviderDetector;
use Illuminate\Support\ServiceProvider;
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
        // Bind interfaces to implementations (automatically handles singletons)
        $this->app->singleton(ComponentStorageInterface::class, ComponentStorage::class);
        $this->app->singleton(ComponentManagerInterface::class, ComponentManager::class);
        $this->app->singleton(PackageInstallerInterface::class, SecurePackageInstaller::class);
        $this->app->singleton(ComponentPersistenceInterface::class, ComponentPersistence::class);

        // Register GitHub client with token from environment
        $this->app->singleton(GithubConnector::class, function () {
            $token = env('GITHUB_TOKEN');
            return new GithubConnector($token);
        });

        // Only concrete services without interfaces
        $this->app->singleton(ComponentDiscoveryService::class);
        $this->app->singleton(ServiceProviderDetector::class);
        $this->app->singleton(ComponentInstallationService::class);
    }
}
