<?php

namespace App\Commands\System;

use App\Services\ComponentManager;
use App\Services\JsonComponentRegistrar;
use App\Services\ServiceProviderDetector;
use Illuminate\Console\Command;

class SyncComponentsCommand extends Command
{
    protected $signature = 'system:sync-components 
                           {--silent : Suppress output except errors}
                           {--force : Force re-registration of all components}';

    protected $description = 'Sync component registry with installed packages (post-install hook)';

    public function handle(
        ComponentManager $componentManager,
        JsonComponentRegistrar $registrar,
        ServiceProviderDetector $detector
    ): int {
        if (! $this->option('silent')) {
            $this->info('🔄 Syncing component registry with installed packages...');
        }

        try {
            // Read the component registry
            $components = $this->getComponentRegistry();

            if (empty($components)) {
                if (! $this->option('silent')) {
                    $this->info('✅ No components registered - nothing to sync');
                }

                return 0;
            }

            $synced = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($components as $name => $config) {
                $result = $this->syncComponent($name, $config, $registrar, $detector);

                switch ($result['status']) {
                    case 'synced':
                        $synced++;
                        if (! $this->option('silent')) {
                            $this->line("   ✅ Synced {$name}");
                        }
                        break;
                    case 'skipped':
                        $skipped++;
                        if (! $this->option('silent')) {
                            $this->line("   ⏭️  Skipped {$name} - {$result['reason']}");
                        }
                        break;
                    case 'error':
                        $errors++;
                        $this->error("   ❌ Error syncing {$name}: {$result['reason']}");
                        break;
                }
            }

            if (! $this->option('silent')) {
                $this->newLine();
                $this->info("📊 Sync complete: {$synced} synced, {$skipped} skipped, {$errors} errors");
                $this->info('🎯 Components are ready for use!');
            }

            return $errors > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error('❌ Component sync failed: '.$e->getMessage());

            return 1;
        }
    }

    private function getComponentRegistry(): array
    {
        $registryPaths = [
            config_path('components.json'),
            ($_SERVER['HOME'] ?? null) ? $_SERVER['HOME'].'/.conduit/config/components.json' : null,
            base_path('config/components.json'),
        ];

        foreach ($registryPaths as $path) {
            if ($path && file_exists($path)) {
                $content = json_decode(file_get_contents($path), true);

                return $content['registry'] ?? [];
            }
        }

        return [];
    }

    private function syncComponent(
        string $name,
        array $config,
        JsonComponentRegistrar $registrar,
        ServiceProviderDetector $detector
    ): array {
        $package = $config['package'] ?? null;

        if (! $package) {
            return ['status' => 'error', 'reason' => 'No package name in registry'];
        }

        // Check if package is actually installed
        if (! $this->isPackageInstalled($package)) {
            return ['status' => 'skipped', 'reason' => 'Package not installed in vendor/'];
        }

        // If force option or service provider missing, re-detect and register
        if ($this->option('force') || empty($config['service_provider'])) {
            return $this->reRegisterComponent($name, $package, $config, $registrar, $detector);
        }

        // Check if service provider class exists
        $serviceProvider = $config['service_provider'];
        if (! class_exists($serviceProvider)) {
            return $this->reRegisterComponent($name, $package, $config, $registrar, $detector);
        }

        return ['status' => 'skipped', 'reason' => 'Already properly registered'];
    }

    private function isPackageInstalled(string $package): bool
    {
        $vendorPath = base_path("vendor/{$package}");

        return is_dir($vendorPath);
    }

    private function reRegisterComponent(
        string $name,
        string $package,
        array $config,
        JsonComponentRegistrar $registrar,
        ServiceProviderDetector $detector
    ): array {
        try {
            // Detect service providers for this package
            $serviceProviders = $detector->detectServiceProviders($package);

            if (empty($serviceProviders)) {
                return ['status' => 'error', 'reason' => 'No service providers found'];
            }

            // Update component registration
            $updatedConfig = array_merge($config, [
                'package' => $package,
                'service_provider' => $serviceProviders[0],
                'commands' => $detector->detectCommands($serviceProviders),
                'status' => 'active',
                'last_synced' => now()->toISOString(),
            ]);

            $registrar->registerComponent($name, $updatedConfig);

            return ['status' => 'synced', 'reason' => 'Service provider registered'];

        } catch (\Exception $e) {
            return ['status' => 'error', 'reason' => $e->getMessage()];
        }
    }
}
