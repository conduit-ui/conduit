<?php

namespace App\Services\Traits;

use App\Services\ComponentResult;
use Symfony\Component\Process\Process;

/**
 * Trait for component installation capabilities
 */
trait InstallsComponents
{
    public function install(string $componentName, array $options = []): ComponentResult
    {
        $packageName = $this->resolvePackageName($componentName);
        $force = $options['force'] ?? false;
        $dev = $options['dev'] ?? false;

        // Check if already installed (unless forced)
        if (!$force && $this->isGloballyInstalled($packageName)) {
            return ComponentResult::failure(
                "Component '{$componentName}' is already installed globally. Use --force to reinstall."
            );
        }

        // Build composer command
        $composerArgs = ['global', 'require', $packageName];
        
        if ($dev) {
            $composerArgs[] = '--dev';
        }

        if ($force) {
            // Remove first, then install
            $removeProcess = new Process(['composer', 'global', 'remove', $packageName]);
            $removeProcess->run();
            // Continue even if removal fails
        }

        // Install the package
        $process = new Process(['composer'] + $composerArgs);
        $process->setTimeout(300); // 5 minutes timeout
        $process->run();

        if ($process->getExitCode() === 0) {
            return ComponentResult::success(
                "Successfully installed '{$componentName}' component!",
                ['component' => $componentName, 'package' => $packageName]
            );
        } else {
            return ComponentResult::failure(
                "Failed to install component '{$componentName}'.",
                $process->getErrorOutput()
            );
        }
    }

    public function forceInstall(string $componentName, array $options = []): ComponentResult
    {
        return $this->install($componentName, array_merge($options, ['force' => true]));
    }

    /**
     * Check if a package is installed globally
     */
    private function isGloballyInstalled(string $packageName): bool
    {
        $process = new Process(['composer', 'global', 'show', $packageName]);
        $process->run();
        
        return $process->getExitCode() === 0;
    }
}