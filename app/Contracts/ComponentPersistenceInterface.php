<?php

namespace App\Contracts;

interface ComponentPersistenceInterface
{
    /**
     * Get all installed components
     */
    public function getInstalled(): array;

    /**
     * Check if a component is installed
     */
    public function isInstalled(string $name): bool;

    /**
     * Get a specific installed component
     */
    public function getComponent(string $name): ?array;

    /**
     * Save/register a component
     */
    public function register(string $name, array $componentInfo): void;

    /**
     * Unregister a component
     */
    public function unregister(string $name): void;

    /**
     * Update component status
     */
    public function updateStatus(string $name, string $status): void;

    /**
     * Get discovery settings
     */
    public function getDiscoverySettings(): array;

    /**
     * Update discovery settings
     */
    public function updateDiscoverySettings(array $settings): void;

    /**
     * Get all settings
     */
    public function getSettings(): array;

    /**
     * Get a specific setting
     */
    public function getSetting(string $key, $default = null);

    /**
     * Update multiple settings
     */
    public function updateSettings(array $settings): void;

    /**
     * Get component registry
     */
    public function getRegistry(): array;

    /**
     * Update component registry
     */
    public function updateRegistry(array $registry): void;

    /**
     * Export all data
     */
    public function export(): array;

    /**
     * Import data
     */
    public function import(array $data): void;

    /**
     * Initialize the persistence layer
     */
    public function initialize(): void;

    /**
     * Migrate from config file
     */
    public function migrateFromConfigFile(): bool;
}