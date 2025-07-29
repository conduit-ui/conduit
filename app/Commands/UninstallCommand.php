<?php

namespace App\Commands;

use App\Services\ComponentManager;
use App\Services\SecurePackageInstaller;
use LaravelZero\Framework\Commands\Command;

/**
 * Streamlined component removal command
 */
class UninstallCommand extends Command
{
    protected $signature = 'uninstall 
                            {component : Component name to uninstall}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Uninstall a Conduit component';

    public function handle(ComponentManager $manager, SecurePackageInstaller $installer): int
    {
        $component = $this->argument('component');
        $force = $this->option('force');

        try {
            // Check if component is installed
            if (!$manager->isInstalled($component)) {
                $this->warn("⚠️  Component '{$component}' is not installed");
                return 1;
            }

            // Get component info
            $componentInfo = $manager->getComponent($component);
            $package = $componentInfo['package'] ?? $component;

            // Confirm removal unless forced
            if (!$force) {
                $confirmed = $this->confirm("Remove component '{$component}' ({$package})?", false);
                if (!$confirmed) {
                    $this->info("🚫 Uninstallation cancelled");
                    return 0;
                }
            }

            $this->info("🗑️  Uninstalling component: {$component}");

            // Remove from composer
            $result = $installer->remove($package);
            
            if ($result->isSuccessful()) {
                // Remove from component registry
                $manager->unregister($component);
                
                $this->info("✅ Successfully uninstalled component: {$component}");
                return 0;
            } else {
                $this->error("❌ Failed to uninstall component: {$component}");
                $this->error("💡 Error: " . $result->getErrorOutput());
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Uninstallation failed: " . $e->getMessage());
            return 1;
        }
    }
}