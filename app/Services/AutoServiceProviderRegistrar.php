<?php

namespace App\Services;

/**
 * Automatically registers service providers in config/app.php for component installation
 */
class AutoServiceProviderRegistrar
{
    private string $configPath;

    public function __construct()
    {
        $this->configPath = config_path('app.php');
    }

    /**
     * Register a service provider in config/app.php
     */
    public function registerServiceProvider(string $serviceProvider): bool
    {
        try {
            $config = $this->loadConfig();

            // Check if already registered
            if (in_array($serviceProvider, $config['providers'])) {
                return true; // Already registered
            }

            // Add to providers array
            $config['providers'][] = $serviceProvider;

            // Write back to file
            return $this->saveConfig($config);

        } catch (\Exception $e) {
            error_log('Error registering service provider: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Unregister a service provider from config/app.php
     */
    public function unregisterServiceProvider(string $serviceProvider): bool
    {
        try {
            $config = $this->loadConfig();

            // Remove from providers array
            $config['providers'] = array_values(array_filter(
                $config['providers'],
                fn ($provider) => $provider !== $serviceProvider
            ));

            // Write back to file
            return $this->saveConfig($config);

        } catch (\Exception $e) {
            error_log('Error unregistering service provider: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Register multiple service providers at once
     */
    public function registerServiceProviders(array $serviceProviders): bool
    {
        try {
            $config = $this->loadConfig();

            foreach ($serviceProviders as $serviceProvider) {
                if (! in_array($serviceProvider, $config['providers'])) {
                    $config['providers'][] = $serviceProvider;
                }
            }

            return $this->saveConfig($config);

        } catch (\Exception $e) {
            error_log('Error registering service providers: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Load config/app.php as an array
     */
    private function loadConfig(): array
    {
        if (! file_exists($this->configPath)) {
            throw new \Exception('Config file not found: '.$this->configPath);
        }

        $config = include $this->configPath;

        if (! is_array($config)) {
            throw new \Exception('Invalid config file format');
        }

        if (! isset($config['providers'])) {
            $config['providers'] = [];
        }

        return $config;
    }

    /**
     * Save config array back to config/app.php with proper formatting
     */
    private function saveConfig(array $config): bool
    {
        $content = $this->generateConfigContent($config);

        return file_put_contents($this->configPath, $content) !== false;
    }

    /**
     * Generate properly formatted PHP config file content
     */
    private function generateConfigContent(array $config): string
    {
        $content = "<?php\n\n";
        $content .= "return [\n";

        foreach ($config as $key => $value) {
            if ($key === 'providers') {
                $content .= "    'providers' => [\n";
                foreach ($value as $index => $provider) {
                    $escapedProvider = addslashes($provider);
                    $content .= "        {$index} => '{$escapedProvider}',\n";
                }
                $content .= "    ],\n";
            } else {
                $content .= '    '.var_export($key, true).' => '.var_export($value, true).",\n";
            }
        }

        $content .= "];\n";

        return $content;
    }

    /**
     * Check if a service provider is registered
     */
    public function isRegistered(string $serviceProvider): bool
    {
        try {
            $config = $this->loadConfig();

            return in_array($serviceProvider, $config['providers']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all registered service providers
     */
    public function getRegisteredProviders(): array
    {
        try {
            $config = $this->loadConfig();

            return $config['providers'];
        } catch (\Exception $e) {
            return [];
        }
    }
}
