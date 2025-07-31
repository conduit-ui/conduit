<?php

namespace App\Services\Traits;

use App\Services\ComponentResult;

/**
 * Trait for component uninstallation capabilities
 */
trait UninstallsComponents
{
    public function uninstall(string $componentName, array $options = []): ComponentResult
    {
        $packageName = $this->resolvePackageName($componentName);

        // Check if component is installed
        if (! $this->isGloballyInstalled($packageName)) {
            return ComponentResult::failure(
                "Component '{$componentName}' is not installed"
            );
        }

        // Remove the package
        $command = sprintf(
            'cd %s && composer global remove %s 2>&1',
            escapeshellarg(getenv('HOME')),
            escapeshellarg($packageName)
        );

        // Use shell_exec for better compatibility
        $output = shell_exec($command.'; echo "EXIT_CODE:$?"');

        // Extract exit code from output
        $lines = explode("\n", trim($output));
        $lastLine = array_pop($lines);
        $exitCode = 0;

        if (strpos($lastLine, 'EXIT_CODE:') === 0) {
            $exitCode = (int) substr($lastLine, 10);
            $output = implode("\n", $lines);
        } else {
            // If we didn't get exit code, put the line back
            $lines[] = $lastLine;
            $output = implode("\n", $lines);
        }

        if ($exitCode === 0) {
            return ComponentResult::success(
                "Successfully uninstalled '{$componentName}' component!",
                ['component' => $componentName, 'package' => $packageName]
            );
        } else {
            return ComponentResult::failure(
                "Failed to uninstall component '{$componentName}'.",
                $output
            );
        }
    }
}
