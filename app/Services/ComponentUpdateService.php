<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Handles component updates via Composer
 */
class ComponentUpdateService
{
    private ComponentInstallationService $installer;

    private JsonComponentRegistrar $registrar;

    public function __construct(
        ComponentInstallationService $installer,
        JsonComponentRegistrar $registrar
    ) {
        $this->installer = $installer;
        $this->registrar = $registrar;
    }

    /**
     * Update a specific component
     */
    public function updateComponent(string $name, ?string $version = null): bool
    {
        $component = $this->registrar->getRegisteredComponents()[$name] ?? null;

        if (! $component) {
            throw new \InvalidArgumentException("Component '{$name}' not found");
        }

        $package = $component['package'];
        $constraint = $version ? ":{$version}" : '';

        $result = Process::timeout(300)
            ->path(base_path())
            ->run([
                'composer',
                'update',
                $package.$constraint,
                '--no-interaction',
                '--prefer-dist',
            ]);

        if ($result->successful()) {
            // Update registry with new version info
            $this->updateComponentVersion($name, $version);

            return true;
        }

        return false;
    }

    /**
     * Update all components that have updates available
     */
    public function updateAll(bool $autoOnly = true): array
    {
        $checker = app(ComponentUpdateChecker::class);
        $updates = $checker->quickCheck();
        $results = [];

        foreach ($updates as $update) {
            $name = $update['name'];

            // Skip breaking changes in auto mode
            if ($autoOnly && $update['priority'] === 'breaking') {
                $results[$name] = 'skipped_breaking';

                continue;
            }

            try {
                $success = $this->updateComponent($name, $update['latest']);
                $results[$name] = $success ? 'updated' : 'failed';
            } catch (\Exception $e) {
                $results[$name] = 'error: '.$e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Show what would be updated (dry run)
     */
    public function dryRun(): array
    {
        $checker = app(ComponentUpdateChecker::class);

        return $checker->quickCheck()->toArray();
    }

    /**
     * Update component version in registry
     */
    private function updateComponentVersion(string $name, ?string $version): void
    {
        // This would update the component registry with the new version
        // For now, we'll let Composer handle version tracking
    }
}
