<?php

namespace App\Services\Traits;

use Symfony\Component\Process\Process;

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
        $process = new Process(['composer', 'global', 'show', $packageName]);
        $process->run();
        
        return $process->getExitCode() === 0;
    }
}