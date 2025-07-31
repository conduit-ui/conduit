<?php

namespace App\Services;

use App\Contracts\ComponentInterface;
use App\Services\Traits\DiscoverComponents;
use App\Services\Traits\InstallsComponents;
use App\Services\Traits\ListsComponents;
use App\Services\Traits\ManagesPackages;
use App\Services\Traits\UninstallsComponents;

/**
 * Composite service for Conduit component management
 *
 * This service composes multiple traits to provide a complete
 * component management solution while maintaining clean separation
 * of concerns through interfaces.
 */
class ComponentService implements ComponentInterface
{
    use DiscoverComponents;
    use InstallsComponents;
    use ListsComponents;
    use ManagesPackages;
    use UninstallsComponents;

    /**
     * Legacy component mappings (only for migration support)
     */
    private array $legacyMappings = [
        'know' => 'jordanpartridge/conduit-know', // Legacy 'know' component
    ];

    public function isInstalled(string $componentName): bool
    {
        $packageName = $this->resolvePackageName($componentName);

        return $this->isGloballyInstalled($packageName);
    }

    public function getComponentInfo(string $componentName): ?array
    {
        $installed = $this->listInstalled();

        foreach ($installed as $component) {
            if ($component['name'] === $componentName) {
                return $component;
            }
        }

        return null;
    }

    public function resolvePackageName(string $componentName): string
    {
        // Handle legacy mappings for migration support
        if (isset($this->legacyMappings[$componentName])) {
            return $this->legacyMappings[$componentName];
        }

        // If it looks like a full package name (vendor/package), use as-is
        if (str_contains($componentName, '/')) {
            return $componentName;
        }

        // Default to jordanpartridge/conduit-{name} pattern
        return "jordanpartridge/conduit-{$componentName}";
    }

    /**
     * Handle legacy component migration
     */
    public function migrateLegacyComponent(string $legacyName, string $newName): ComponentResult
    {
        $legacyPackage = $this->resolvePackageName($legacyName);

        // Only migrate if legacy component is installed
        if ($this->isGloballyInstalled($legacyPackage)) {
            // Remove legacy component
            $removeResult = $this->uninstall($legacyName);

            if (! $removeResult->isSuccessful()) {
                return ComponentResult::failure(
                    "Failed to remove legacy component '{$legacyName}': ".$removeResult->getMessage()
                );
            }
        }

        // Install new component
        return $this->install($newName);
    }
}
