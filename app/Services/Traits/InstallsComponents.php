<?php

namespace App\Services\Traits;

use App\Services\ComponentResult;

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
        if (! $force && $this->isGloballyInstalled($packageName)) {
            return ComponentResult::failure(
                "Component '{$componentName}' is already installed",
                '💡 Use --force to reinstall'
            );
        }

        // Build composer command
        $composerArgs = ['global', 'require', $packageName];

        if ($dev) {
            $composerArgs[] = '--dev';
        }

        if ($force) {
            // Remove first, then install
            $removeCommand = sprintf(
                'cd %s && composer global remove %s 2>&1',
                escapeshellarg(getenv('HOME')),
                escapeshellarg($packageName)
            );
            exec($removeCommand);
            // Continue even if removal fails
        }

        // Install the package
        $command = 'composer '.implode(' ', $composerArgs);

        // Use shell execution to ensure it runs in the user's environment
        $fullCommand = sprintf(
            'cd %s && %s 2>&1',
            escapeshellarg(getenv('HOME')),
            $command
        );

        $output = [];
        $exitCode = null;
        exec($fullCommand, $output, $exitCode);

        // Convert exec results to match Process behavior
        $outputString = implode("\n", $output);
        $success = $exitCode === 0;

        if ($success) {
            return ComponentResult::success(
                "Successfully installed '{$componentName}' component!",
                ['component' => $componentName, 'package' => $packageName]
            );
        } else {
            return ComponentResult::failure(
                "Failed to install component '{$componentName}'.",
                $outputString
            );
        }
    }

    public function forceInstall(string $componentName, array $options = []): ComponentResult
    {
        return $this->install($componentName, array_merge($options, ['force' => true]));
    }
}
