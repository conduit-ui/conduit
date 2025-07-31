<?php

namespace App\Contracts;

use App\Services\ComponentResult;

/**
 * Interface for component uninstallation capabilities
 */
interface UninstallsComponents
{
    /**
     * Uninstall a component
     */
    public function uninstall(string $componentName, array $options = []): ComponentResult;
}
