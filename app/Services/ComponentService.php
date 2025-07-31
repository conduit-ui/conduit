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
    use ManagesPackages;
    use InstallsComponents;
    use UninstallsComponents;
    use ListsComponents; 
    use DiscoverComponents;

    /**
     * Known component mappings
     */
    private array $knownComponents = [
        'knowledge' => 'jordanpartridge/conduit-knowledge',
        'know' => 'jordanpartridge/conduit-know', // Legacy support
        'spotify' => 'jordanpartridge/conduit-spotify',
        'env-manager' => 'jordanpartridge/conduit-env-manager',
        'docker' => 'jordanpartridge/conduit-docker',
        'github' => 'jordanpartridge/conduit-github',
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
        // Return known mapping or assume jordanpartridge/conduit-{name} pattern
        return $this->knownComponents[$componentName] ?? "jordanpartridge/conduit-{$componentName}";
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
            
            if (!$removeResult->isSuccessful()) {
                return ComponentResult::failure(
                    "Failed to remove legacy component '{$legacyName}': " . $removeResult->getMessage()
                );
            }
        }

        // Install new component
        return $this->install($newName);
    }
}