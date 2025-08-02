<?php

namespace App\Contracts;

/**
 * Core interface for Conduit component management
 */
interface ComponentInterface extends DiscoverComponents, InstallsComponents, ListsComponents, UninstallsComponents
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

    /**
     * Get a global setting value
     */
    public function getGlobalSetting(string $key, mixed $default = null): mixed;

    /**
     * Update a global setting value
     */
    public function updateGlobalSetting(string $key, mixed $value): void;

    /**
     * Get all global settings
     */
    public function getGlobalSettings(): array;
}
