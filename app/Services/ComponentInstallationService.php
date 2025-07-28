<?php

namespace App\Services;

/**
 * High-level component installation orchestration service
 */
class ComponentInstallationService
{
    public function __construct(
        private SecurePackageInstaller $installer,
        private ComponentManager $manager,
        private ServiceProviderDetector $detector,
        private AutoServiceProviderRegistrar $registrar
    ) {}

    /**
     * Install a component with full lifecycle management
     */
    public function installComponent(string $componentName, array $component): ComponentInstallationResult
    {
        try {
            // Step 1: Secure package installation
            $installResult = $this->installer->install($component);

            if (! $installResult->isSuccessful()) {
                return ComponentInstallationResult::failed(
                    'Composer installation failed: '.$installResult->getErrorOutput(),
                    $installResult
                );
            }

            // Step 2: Detect service providers
            $serviceProviders = $this->detector->detectServiceProviders($component['full_name']);

            // Step 3: Auto-register service providers in config/app.php
            if (! empty($serviceProviders)) {
                $registrationSuccess = $this->registrar->registerServiceProviders($serviceProviders);
                if (! $registrationSuccess) {
                    return ComponentInstallationResult::failed(
                        'Failed to register service providers in config/app.php',
                        $installResult
                    );
                }
            }

            // Step 4: Detect commands
            $commands = $this->detector->detectCommands($serviceProviders);

            // Step 5: Register component
            $componentInfo = [
                'package' => $component['full_name'],
                'description' => $component['description'],
                'commands' => $commands,
                'env_vars' => [],
                'service_providers' => $serviceProviders,
                'topics' => $component['topics'],
                'url' => $component['url'],
                'stars' => $component['stars'],
            ];

            $this->manager->register($componentName, $componentInfo);

            return ComponentInstallationResult::success($componentInfo, $commands);

        } catch (\Exception $e) {
            return ComponentInstallationResult::failed($e->getMessage());
        }
    }

    /**
     * Uninstall a component
     */
    public function uninstallComponent(string $componentName): bool
    {
        try {
            // Get component info before unregistering
            $componentInfo = $this->manager->getComponent($componentName);

            if (! $componentInfo) {
                return false; // Component not found
            }

            // Step 1: Remove composer package
            $packageName = $componentInfo['package'] ?? null;
            if ($packageName) {
                $removeResult = $this->installer->remove($packageName);
                if (! $removeResult->isSuccessful()) {
                    error_log('Failed to remove composer package: '.$removeResult->getErrorOutput());
                    // Continue anyway to clean up registration
                }
            }

            // Step 2: Unregister service providers from config/app.php
            if (isset($componentInfo['service_providers']) && ! empty($componentInfo['service_providers'])) {
                foreach ($componentInfo['service_providers'] as $serviceProvider) {
                    $this->registrar->unregisterServiceProvider($serviceProvider);
                }
            }

            // Step 3: Unregister component from manager
            $this->manager->unregister($componentName);

            return true;
        } catch (\Exception $e) {
            error_log('Error uninstalling component: '.$e->getMessage());

            return false;
        }
    }
}
