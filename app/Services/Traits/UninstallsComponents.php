<?php

namespace App\Services\Traits;

use App\Services\ComponentResult;
use Symfony\Component\Process\Process;

/**
 * Trait for component uninstallation capabilities
 */
trait UninstallsComponents
{
    public function uninstall(string $componentName, array $options = []): ComponentResult
    {
        $packageName = $this->resolvePackageName($componentName);

        // Check if component is installed
        if (!$this->isGloballyInstalled($packageName)) {
            return ComponentResult::failure(
                "Component '{$componentName}' is not installed globally."
            );
        }

        // Remove the package
        $process = new Process(['composer', 'global', 'remove', $packageName]);
        $process->setTimeout(300); // 5 minutes timeout
        $process->run();

        if ($process->getExitCode() === 0) {
            return ComponentResult::success(
                "Successfully uninstalled '{$componentName}' component!",
                ['component' => $componentName, 'package' => $packageName]
            );
        } else {
            return ComponentResult::failure(
                "Failed to uninstall component '{$componentName}'.",
                $process->getErrorOutput()
            );
        }
    }

}