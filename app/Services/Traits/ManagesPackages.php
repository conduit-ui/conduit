<?php

namespace App\Services\Traits;

/**
 * Trait for common package management operations
 */
trait ManagesPackages
{
    /**
     * Check if a package is installed globally
     */
    protected function isGloballyInstalled(string $packageName): bool
    {
        $command = sprintf(
            'cd %s && composer global show %s 2>&1',
            escapeshellarg(getenv('HOME')),
            escapeshellarg($packageName)
        );

        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }
}
