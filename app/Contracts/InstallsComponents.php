<?php

namespace App\Contracts;

use App\Services\ComponentResult;

/**
 * Interface for component installation capabilities
 */
interface InstallsComponents
{
    /**
     * Install a component
     */
    public function install(string $componentName, array $options = []): ComponentResult;

    /**
     * Force reinstall a component
     */
    public function forceInstall(string $componentName, array $options = []): ComponentResult;
}