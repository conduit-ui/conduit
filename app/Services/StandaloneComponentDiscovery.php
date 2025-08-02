<?php

namespace App\Services;

use App\Services\Security\ComponentSecurityValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Filesystem\Path;

class StandaloneComponentDiscovery
{
    private array $componentPaths;

    private const CACHE_KEY = 'conduit.components';

    private const CACHE_TTL = 3600; // 1 hour

    private ?Collection $cachedComponents = null;

    private array $excludePatterns;

    private ComponentSecurityValidator $securityValidator;

    public function __construct(ComponentSecurityValidator $securityValidator = null)
    {
        $this->securityValidator = $securityValidator ?? new ComponentSecurityValidator();
        $this->componentPaths = [
            // Core components (ship with conduit)
            base_path('components/core'),

            // Dev components (development only)
            base_path('components/dev'),

            // User components (installed via conduit)
            $this->getHomeDirectory().'/.conduit/components',
        ];

        $this->excludePatterns = [
            'list', 'help', 'delegated', 'make:', 'test', 'build',
        ];
    }

    /**
     * Discover all available standalone components
     */
    public function discover(): Collection
    {
        // Try to retrieve from application-level cache first
        $cachedComponents = Cache::get(self::CACHE_KEY);
        if ($cachedComponents !== null) {
            return $cachedComponents;
        }

        // Fallback to instance-level cache
        if ($this->cachedComponents !== null) {
            return $this->cachedComponents;
        }

        $components = collect();

        foreach ($this->componentPaths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $componentDirs = glob($path.'/*', GLOB_ONLYDIR);

            foreach ($componentDirs as $componentDir) {
                try {
                    $componentName = basename($componentDir);
                    $binaryPath = $componentDir.'/'.$componentName;

                    // Validate component and binary paths
                    $this->securityValidator->validateComponentPath($componentDir);
                    $this->securityValidator->validateComponentName($componentName);
                    $validatedBinaryPath = $this->securityValidator->validateBinaryPath($binaryPath);

                    // Check if component binary exists and is accessible
                    if (file_exists($validatedBinaryPath) && is_executable($validatedBinaryPath) && is_readable($validatedBinaryPath)) {
                        // Additional integrity check
                        $this->securityValidator->validateBinaryIntegrity($validatedBinaryPath);
                        
                        $components->put($componentName, [
                            'name' => $componentName,
                            'path' => $componentDir,
                            'binary' => $validatedBinaryPath,
                            'commands' => $this->getComponentCommands($validatedBinaryPath),
                        ]);
                    }
                } catch (\InvalidArgumentException $e) {
                    // Log security violation but continue discovery
                    // This prevents a malicious component from breaking discovery
                    if (app()->bound('log')) {
                        app('log')->warning('Component discovery security check failed', [
                            'component' => $componentName ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $this->cachedComponents = $components;

        // Cache for 1 hour
        Cache::put(self::CACHE_KEY, $components, self::CACHE_TTL);

        return $components;
    }

    /**
     * Get published commands from a component (only commands meant for conduit delegation)
     */
    private function getComponentCommands(string $binaryPath): array
    {
        try {
            // Validate binary path first
            $validatedBinaryPath = $this->securityValidator->validateBinaryPath($binaryPath);
            
            $componentDir = dirname($validatedBinaryPath);
            $configPath = $componentDir.'/config/commands.php';

            // Try to read published commands from component's config
            if (file_exists($configPath)) {
                // Validate config path is within component directory
                $this->securityValidator->validateComponentPath($configPath);
                
                $config = @include $configPath;
                if (! is_array($config)) {
                    $config = [];
                }
                $publishedCommands = $config['published'] ?? [];

                if (! empty($publishedCommands)) {
                    // Validate each command name
                    $validatedCommands = [];
                    foreach ($publishedCommands as $cmd) {
                        try {
                            $validatedCommands[] = $this->securityValidator->validateCommandName($cmd);
                        } catch (\InvalidArgumentException $e) {
                            // Skip invalid command names
                        }
                    }
                    return $validatedCommands;
                }
            }

            // Fallback: parse list output but filter out dev commands
            // Use Process instead of shell_exec for safety
            $process = new \Symfony\Component\Process\Process([$validatedBinaryPath, 'list', '--raw']);
            $process->setTimeout(5); // 5 second timeout
            $process->run();
            
            if (!$process->isSuccessful()) {
                return [];
            }
            
            $output = $process->getOutput();

            if (! $output) {
                return [];
            }

            $commands = [];
            $lines = explode("\n", trim($output));

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
                foreach ($this->excludePatterns as $pattern) {
                    if ($command === $pattern || str_starts_with($command, $pattern)) {
                        $shouldExclude = true;
                        break;
                    }
                }

                if (! $shouldExclude) {
                    try {
                        // Validate command name before adding
                        $validatedCommand = $this->securityValidator->validateCommandName($command);
                        $commands[] = $validatedCommand;
                    } catch (\InvalidArgumentException $e) {
                        // Skip invalid command names
                    }
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
     * Clear the component cache (useful for testing or when components change)
     */
    public function clearCache(): void
    {
        $this->cachedComponents = null;
    }

    /**
     * Set exclude patterns for filtering commands
     */
    public function setExcludePatterns(array $patterns): void
    {
        $this->excludePatterns = $patterns;
        $this->clearCache(); // Clear cache when patterns change
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
