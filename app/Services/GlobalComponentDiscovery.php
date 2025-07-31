<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GlobalComponentDiscovery
{
    private array $searchPaths = [];

    public function __construct()
    {
        $home = $this->getHomeDirectory();

        if ($home) {
            $this->searchPaths = [
                $home.'/.composer/vendor/*/*/conduit.json',
                $home.'/.config/composer/vendor/*/*/conduit.json',
            ];
        }
    }

    /**
     * Get the user's home directory in a cross-platform way
     */
    private function getHomeDirectory(): ?string
    {
        // Try environment variables in order of preference
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        // Windows-specific fallback
        if (! $home && PHP_OS_FAMILY === 'Windows') {
            $homeDrive = getenv('HOMEDRIVE');
            $homePath = getenv('HOMEPATH');
            if ($homeDrive && $homePath) {
                $home = $homeDrive.$homePath;
            }
        }

        // Final fallback to temp directory
        if (! $home) {
            $home = sys_get_temp_dir();
        }

        return $home;
    }

    public function discover(): Collection
    {
        $components = collect();

        foreach ($this->searchPaths as $pattern) {
            $manifests = glob($pattern);

            if (! $manifests) {
                continue;
            }

            foreach ($manifests as $manifestPath) {
                try {
                    $manifest = json_decode(file_get_contents($manifestPath), true);

                    if (! $manifest || ! isset($manifest['name'])) {
                        continue;
                    }

                    // Extract package info from path
                    preg_match('|vendor/([^/]+)/([^/]+)/conduit\.json$|', $manifestPath, $matches);
                    $vendor = $matches[1] ?? 'unknown';
                    $package = $matches[2] ?? 'unknown';

                    // Get providers from manifest or try to detect from composer.json
                    $providers = $manifest['providers'] ?? [];

                    if (empty($providers)) {
                        // Try to get from composer.json Laravel extra section
                        $composerPath = dirname($manifestPath).'/composer.json';
                        if (file_exists($composerPath)) {
                            $composer = json_decode(file_get_contents($composerPath), true);
                            $providers = $composer['extra']['laravel']['providers'] ?? [];
                        }
                    }

                    $components->push([
                        'name' => $manifest['name'],
                        'package' => "$vendor/$package",
                        'description' => $manifest['description'] ?? '',
                        'version' => $manifest['version'] ?? 'unknown',
                        'path' => dirname($manifestPath),
                        'global' => true,
                        'providers' => $providers,
                    ]);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $components;
    }

    public function loadComponent(array $component): void
    {
        $componentPath = $component['path'];

        // Validate component path to prevent directory traversal
        $realPath = realpath($componentPath);
        if (! $realPath || ! $this->isPathAllowed($realPath)) {
            throw new \RuntimeException("Invalid component path: {$componentPath}");
        }

        // Load component's autoloader if it exists
        $autoloadPath = $realPath.'/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            // Validate autoloader path
            $realAutoloadPath = realpath($autoloadPath);
            if (! $realAutoloadPath || ! str_starts_with($realAutoloadPath, $realPath)) {
                throw new \RuntimeException("Invalid autoloader path for component: {$component['name']}");
            }

            require_once $realAutoloadPath;
        }

        // Register service providers
        foreach ($component['providers'] as $provider) {
            // Validate provider class name
            if (! $this->isValidClassName($provider)) {
                continue;
            }

            if (class_exists($provider)) {
                app()->register($provider);
            }
        }
    }

    /**
     * Check if a path is within allowed directories
     */
    private function isPathAllowed(string $path): bool
    {
        $home = $this->getHomeDirectory();
        if (! $home) {
            return false;
        }

        $allowedPaths = [
            realpath($home.'/.composer/vendor'),
            realpath($home.'/.config/composer/vendor'),
        ];

        foreach ($allowedPaths as $allowedPath) {
            if ($allowedPath && str_starts_with($path, $allowedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that a class name is safe to use
     */
    private function isValidClassName(string $className): bool
    {
        // Basic validation for class name format
        return preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/', $className) === 1;
    }
}
