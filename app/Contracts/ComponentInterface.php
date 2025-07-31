<?php

namespace App\Contracts;

/**
 * Core interface for Conduit component management
 */
interface ComponentInterface extends InstallsComponents, UninstallsComponents, ListsComponents, DiscoverComponents
{
    /**
     * Check if a component is installed
     */
    public function isInstalled(string $componentName): bool;

    /**
     * Get component information
     */
    public function getComponentInfo(string $componentName): ?array;

    /**
     * Resolve component name to package name
     */
    public function resolvePackageName(string $componentName): string;
}