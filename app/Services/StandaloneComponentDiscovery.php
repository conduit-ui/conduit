<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StandaloneComponentDiscovery
{
    private array $componentPaths;

    public function __construct()
    {
        $this->componentPaths = [
            // Core components (ship with conduit)
            base_path('components/core'),

            // Dev components (development only)
            base_path('components/dev'),

            // User components (installed via conduit)
            $this->getHomeDirectory().'/.conduit/components',
        ];
    }

    /**
     * Discover all available standalone components
     */
    public function discover(): Collection
    {
        $components = collect();

        foreach ($this->componentPaths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $componentDirs = glob($path.'/*', GLOB_ONLYDIR);

            foreach ($componentDirs as $componentDir) {
                $componentName = basename($componentDir);
                $binaryPath = $componentDir.'/'.$componentName;

                // Check if component binary exists
                if (file_exists($binaryPath) && is_executable($binaryPath)) {
                    $components->put($componentName, [
                        'name' => $componentName,
                        'path' => $componentDir,
                        'binary' => $binaryPath,
                        'commands' => $this->getComponentCommands($binaryPath),
                    ]);
                }
            }
        }

        return $components;
    }

    /**
     * Get published commands from a component (only commands meant for conduit delegation)
     */
    private function getComponentCommands(string $binaryPath): array
    {
        try {
            $componentDir = dirname($binaryPath);
            $configPath = $componentDir.'/config/commands.php';

            // Try to read published commands from component's config
            if (file_exists($configPath)) {
                $config = include $configPath;
                $publishedCommands = $config['published'] ?? [];

                if (! empty($publishedCommands)) {
                    return $publishedCommands;
                }
            }

            // Fallback: parse list output but filter out dev commands
            $output = shell_exec($binaryPath.' list --raw 2>/dev/null');

            if (! $output) {
                return [];
            }

            $commands = [];
            $lines = explode("\n", trim($output));

            // Filter out development/internal commands
            $excludePatterns = [
                'list', 'help', 'delegated', 'make:', 'test', 'build',
            ];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, ' ')) {
                    continue;
                }

                // Extract command name (first word)
                $parts = explode(' ', $line);
                $command = $parts[0] ?? '';

                if (empty($command)) {
                    continue;
                }

                // Skip development/internal commands
                $shouldExclude = false;
                foreach ($excludePatterns as $pattern) {
                    if ($command === $pattern || str_starts_with($command, $pattern)) {
                        $shouldExclude = true;
                        break;
                    }
                }

                if (! $shouldExclude) {
                    $commands[] = $command;
                }
            }

            return $commands;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if a component exists
     */
    public function hasComponent(string $componentName): bool
    {
        return $this->discover()->has($componentName);
    }

    /**
     * Get component info
     */
    public function getComponent(string $componentName): ?array
    {
        return $this->discover()->get($componentName);
    }

    /**
     * Get the user's home directory
     */
    private function getHomeDirectory(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        if (! $home && PHP_OS_FAMILY === 'Windows') {
            $homeDrive = getenv('HOMEDRIVE');
            $homePath = getenv('HOMEPATH');
            if ($homeDrive && $homePath) {
                $home = $homeDrive.$homePath;
            }
        }

        return $home ?: sys_get_temp_dir();
    }
}
