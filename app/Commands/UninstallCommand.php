<?php

namespace App\Commands;

use App\Services\ComponentService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

/**
 * Simple component uninstallation command using composer global remove
 *
 * Replaces the complex ComponentsCommand uninstall functionality with
 * direct composer global operations for cleaner architecture.
 */
class UninstallCommand extends Command
{
    protected $signature = 'uninstall 
                            {component : Component name to uninstall (e.g. knowledge, spotify)}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Uninstall a Conduit component using composer global remove';

    public function handle(ComponentService $componentService): int
    {
        $componentName = $this->argument('component');
        $force = $this->option('force');

        // Check if component is installed
        if (! $componentService->isInstalled($componentName)) {
            warning("⚠️  Component '{$componentName}' is not installed");

            return Command::SUCCESS;
        }

        // Confirm removal unless forced
        if (! $force) {
            $packageName = $componentService->resolvePackageName($componentName);
            $confirmed = confirm("Remove component '{$componentName}' ({$packageName})?", false);
            if (! $confirmed) {
                info('🚫 Uninstallation cancelled');

                return Command::SUCCESS;
            }
        }

        // Remove the package
        info("🗑️  Uninstalling component: {$componentName}");

        $result = spin(
            fn () => $componentService->uninstall($componentName),
            "Removing {$componentName}..."
        );

        if ($result->isSuccessful()) {
            info('✅ '.$result->getMessage());

            return Command::SUCCESS;
        } else {
            error('❌ '.$result->getMessage());
            if ($result->getErrorOutput()) {
                error($result->getErrorOutput());
            }

            return Command::FAILURE;
        }
    }
}
