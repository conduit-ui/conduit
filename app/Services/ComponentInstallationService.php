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
        private JsonComponentRegistrar $registrar
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

            // Step 3: Register component in JSON registry
            $componentData = [
                'full_name' => $component['full_name'],
                'description' => $component['description'] ?? "Conduit component for {$componentName} functionality",
                'service_provider' => $serviceProviders[0] ?? null, // Use first service provider
                'commands' => $this->detector->detectCommands($serviceProviders),
            ];

            $registrationSuccess = $this->registrar->registerComponent($componentName, $componentData);
            if (! $registrationSuccess) {
                return ComponentInstallationResult::failed(
                    'Failed to register component in local registry',
                    $installResult
                );
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

            // Step 2: Unregister component from JSON registry
            $this->registrar->unregisterComponent($componentName);

            // Step 3: Unregister component from manager (if still using old system)
            $this->manager->unregister($componentName);

            return true;
        } catch (\Exception $e) {
            error_log('Error uninstalling component: '.$e->getMessage());

            return false;
        }
    }
}
