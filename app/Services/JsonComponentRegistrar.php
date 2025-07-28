<?php

namespace App\Services;

/**
 * Manages component registration in the local JSON registry
 */
class JsonComponentRegistrar
{
    private string $componentsFile;

    public function __construct(?string $configPath = null)
    {
        $this->componentsFile = $configPath ?? (function_exists('config_path') ? config_path('components.json') : __DIR__.'/../../config/components.json');
    }

    /**
     * Register a component in the local JSON registry
     */
    public function registerComponent(string $name, array $componentData): bool
    {
        try {
            $registry = $this->loadRegistry();

            $registry['registry'][$name] = [
                'package' => $componentData['full_name'] ?? $componentData['package'] ?? "unknown/{$name}",
                'service_provider' => $componentData['service_provider'] ?? null,
                'commands' => $componentData['commands'] ?? [],
                'description' => $componentData['description'] ?? "Conduit component for {$name} functionality",
                'status' => 'active',
                'installed_at' => now()->toISOString(),
            ];

            return $this->saveRegistry($registry);
        } catch (\Exception $e) {
            error_log("Failed to register component {$name}: ".$e->getMessage());
            
            // Re-throw for better error handling upstream
            throw new \RuntimeException(
                "Component registration failed for '{$name}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Unregister a component from the local JSON registry
     */
    public function unregisterComponent(string $name): bool
    {
        try {
            $registry = $this->loadRegistry();

            if (isset($registry['registry'][$name])) {
                unset($registry['registry'][$name]);

                return $this->saveRegistry($registry);
            }

            return true; // Already unregistered
        } catch (\Exception $e) {
            error_log("Failed to unregister component {$name}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Check if a component is registered
     */
    public function isRegistered(string $name): bool
    {
        try {
            $registry = $this->loadRegistry();

            return isset($registry['registry'][$name]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all registered components
     */
    public function getRegisteredComponents(): array
    {
        try {
            $registry = $this->loadRegistry();

            return $registry['registry'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Load the component registry from JSON file
     */
    private function loadRegistry(): array
    {
        if (! file_exists($this->componentsFile)) {
            return [
                'registry' => [],
                'settings' => [
                    'auto_discover' => true,
                    'validate_components' => true,
                    'fail_on_missing' => false,
                ],
            ];
        }

        $content = file_get_contents($this->componentsFile);
        $registry = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in components registry: '.json_last_error_msg());
        }

        return $registry;
    }

    /**
     * Save the component registry to JSON file
     */
    private function saveRegistry(array $registry): bool
    {
        $content = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents($this->componentsFile, $content) !== false;
    }

    /**
     * Update component status (active/inactive)
     */
    public function setComponentStatus(string $name, string $status): bool
    {
        try {
            $registry = $this->loadRegistry();

            if (isset($registry['registry'][$name])) {
                $registry['registry'][$name]['status'] = $status;

                return $this->saveRegistry($registry);
            }

            return false; // Component not found
        } catch (\Exception $e) {
            error_log("Failed to set status for component {$name}: ".$e->getMessage());

            return false;
        }
    }
}
