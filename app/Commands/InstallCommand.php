<?php

namespace App\Commands;

use App\Services\ComponentInstallationService;
use App\Services\ComponentManager;
use LaravelZero\Framework\Commands\Command;

/**
 * Streamlined component installation command
 */
class InstallCommand extends Command
{
    protected $signature = 'install 
                            {component : Component name or package to install}
                            {--force : Force reinstallation if already installed}';

    protected $description = 'Install a Conduit component';

    public function handle(ComponentManager $manager, ComponentInstallationService $installer): int
    {
        $component = $this->argument('component');
        $force = $this->option('force');

        try {
            $this->info("🔍 Installing component: {$component}");
            
            // Check if already installed (unless force)
            if (!$force && $manager->isInstalled($component)) {
                $this->warn("⚠️  Component '{$component}' is already installed");
                $this->info("💡 Use --force to reinstall");
                return 1;
            }

            // First, discover the component
            $availableComponents = $manager->discoverComponents();
            
            // Find the component in available list
            $componentData = collect($availableComponents)->firstWhere('name', $component);
            
            if (!$componentData) {
                $this->error("❌ Component '{$component}' not found");
                $this->info("💡 Available components: " . collect($availableComponents)->pluck('name')->join(', '));
                return 1;
            }
            
            // Perform installation
            $result = $installer->installComponent($component, $componentData);
            
            if ($result->isSuccessful()) {
                $this->info("✅ Successfully installed component: {$component}");
                $this->info("🎯 Run 'conduit list' to see available commands");
                return 0;
            } else {
                $this->error("❌ Failed to install component: {$component}");
                $this->error("💡 Error: " . $result->getErrorMessage());
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Installation failed: " . $e->getMessage());
            return 1;
        }
    }
}