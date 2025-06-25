<?php

namespace App\Providers;

use App\Services\ComponentInstallationService;
use App\Services\ComponentStorage;
use App\Services\SecurePackageInstaller;
use App\Services\ServiceProviderDetector;
use Illuminate\Support\ServiceProvider;

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
        // Register component storage service
        $this->app->singleton(ComponentStorage::class);
        
        // Register component installation services
        $this->app->singleton(SecurePackageInstaller::class);
        $this->app->singleton(ServiceProviderDetector::class);
        $this->app->singleton(ComponentInstallationService::class);
    }
}
