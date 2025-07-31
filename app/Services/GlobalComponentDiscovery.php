<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GlobalComponentDiscovery
{
    private array $searchPaths = [];

    public function __construct()
    {
        $home = getenv('HOME');
        $this->searchPaths = [
            $home.'/.composer/vendor/*/*/conduit.json',
            $home.'/.config/composer/vendor/*/*/conduit.json',
        ];
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

        // Load component's autoloader if it exists
        $autoloadPath = $componentPath.'/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }

        // Register service providers
        foreach ($component['providers'] as $provider) {
            if (class_exists($provider)) {
                app()->register($provider);
            }
        }
    }
}
