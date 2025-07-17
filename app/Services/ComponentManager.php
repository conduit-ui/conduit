<?php

namespace App\Services;

use App\Contracts\ComponentManagerInterface;
use Carbon\Carbon;
use GuzzleHttp\Client;
use JordanPartridge\GithubClient\Contracts\GithubConnectorInterface;

/**
 * Service for managing Conduit components
 *
 * Handles component discovery, installation, registration, and lifecycle management.
 * Components are external packages that extend Conduit through service providers.
 *
 * Now uses database storage instead of config file mutations.
 */
class ComponentManager implements ComponentManagerInterface
{
    public function __construct(
        private ComponentStorage $storage,
        private ?GithubConnectorInterface $githubConnector = null
    ) {}

    /**
     * Initialize database storage if not already done
     */
    public function ensureStorageInitialized(): void
    {
        $this->configureDatabaseIfNeeded();

        if (! $this->storage->isDatabaseInitialized()) {
            throw new \RuntimeException(
                'Conduit database not initialized. Run: php conduit storage:init'
            );
        }
    }

    /**
     * Configure database connection for Conduit storage
     */
    private function configureDatabaseIfNeeded(): void
    {
        $dbPath = $this->getDatabasePath();

        if (file_exists($dbPath)) {
            config([
                'database.default' => 'conduit_sqlite',
                'database.connections.conduit_sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $dbPath,
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ],
            ]);
        }
    }

    /**
     * Get the database path
     */
    private function getDatabasePath(): string
    {
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
        $conduitDir = $homeDir.'/.conduit';

        return $conduitDir.'/conduit.sqlite';
    }

    public function isInstalled(string $name): bool
    {
        $this->ensureStorageInitialized();

        return $this->storage->isInstalled($name);
    }

    public function getInstalled(): array
    {
        $this->ensureStorageInitialized();

        return $this->storage->getInstalled();
    }

    public function getRegistry(): array
    {
        // Registry is still from static config as it's read-only
        return config('components.registry', []);
    }

    public function register(string $name, array $componentInfo, ?string $version = null): void
    {
        $this->ensureStorageInitialized();

        $componentInfo['status'] = 'active';
        $componentInfo['installed_at'] = Carbon::now()->toISOString();

        $this->storage->registerComponent($name, $componentInfo, $version);
    }

    public function unregister(string $name): void
    {
        $this->ensureStorageInitialized();
        $this->storage->unregisterComponent($name);
    }

    public function discoverComponents(): array
    {
        $topic = config('components.discovery.github_topic', 'conduit-component');

        try {
            // Use authenticated GitHub client if available, otherwise fallback to direct HTTP
            if ($this->githubConnector) {
                return $this->discoverComponentsWithAuth($topic);
            }

            $client = new Client([
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Conduit/1.0',
                    'Accept' => 'application/vnd.github.v3+json',
                ],
            ]);

            $response = $client->get('https://api.github.com/search/repositories', [
                'query' => [
                    'q' => "topic:{$topic}",
                    'sort' => 'updated',
                    'order' => 'desc',
                    'per_page' => 50, // Limit results
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                error_log('GitHub API Error: '.$response->getStatusCode().' '.$response->getBody());

                // Try fallback to local registry if configured
                if (config('components.discovery.fallback_to_local', false)) {
                    return $this->getLocalRegistry();
                }

                return [];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['items'])) {
                error_log('GitHub API: No items in response: '.json_encode($data));

                return config('components.discovery.fallback_to_local', false) ? $this->getLocalRegistry() : [];
            }

            $components = collect($data['items'])
                ->filter(function ($repo) {
                    // Filter out archived or disabled repos
                    return ! ($repo['archived'] ?? false) && ! ($repo['disabled'] ?? false);
                })
                ->map(function ($repo) {
                    return [
                        'name' => $repo['name'],
                        'full_name' => $repo['full_name'],
                        'description' => $repo['description'] ?? 'No description available',
                        'url' => $repo['html_url'],
                        'topics' => $repo['topics'] ?? [],
                        'updated_at' => $repo['updated_at'],
                        'stars' => $repo['stargazers_count'] ?? 0,
                        'language' => $repo['language'] ?? 'Unknown',
                        'license' => $repo['license']['name'] ?? 'No license',
                    ];
                })
                ->toArray();

            // Log discovery success
            error_log('Component discovery: Found '.count($components).' components');

            return $components;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            error_log('GitHub API Request failed: '.$e->getMessage());

            // Check if it's a rate limit issue
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 403) {
                error_log('GitHub API rate limit exceeded');
            }

            return config('components.discovery.fallback_to_local', false) ? $this->getLocalRegistry() : [];

        } catch (\Exception $e) {
            error_log('Component discovery error: '.$e->getMessage());

            return config('components.discovery.fallback_to_local', false) ? $this->getLocalRegistry() : [];
        }
    }

    /**
     * Discover components using authenticated GitHub client
     */
    private function discoverComponentsWithAuth(string $topic): array
    {
        try {
            $searchRequest = $this->githubConnector->send(
                new \JordanPartridge\GithubClient\Requests\SearchRepositoriesRequest([
                    'q' => "topic:{$topic}",
                    'sort' => 'updated',
                    'order' => 'desc',
                    'per_page' => 50,
                ])
            );

            $data = $searchRequest->json();

            if (! isset($data['items'])) {
                return $this->getLocalRegistry();
            }

            return collect($data['items'])
                ->filter(function ($repo) {
                    return ! ($repo['archived'] ?? false) && ! ($repo['disabled'] ?? false);
                })
                ->map(function ($repo) {
                    return [
                        'name' => $repo['name'],
                        'full_name' => $repo['full_name'],
                        'description' => $repo['description'] ?? 'No description available',
                        'html_url' => $repo['html_url'],
                        'clone_url' => $repo['clone_url'],
                        'updated_at' => $repo['updated_at'],
                        'stargazers_count' => $repo['stargazers_count'] ?? 0,
                        'topics' => $repo['topics'] ?? [],
                        'source' => 'github_authenticated',
                    ];
                })
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            error_log('Authenticated GitHub discovery error: '.$e->getMessage());

            return $this->getLocalRegistry();
        }
    }

    /**
     * Get components from local registry as fallback
     */
    private function getLocalRegistry(): array
    {
        $registry = config('components.registry', []);

        return collect($registry)->map(function ($component, $name) {
            return array_merge($component, [
                'name' => $name,
                'full_name' => $component['package'] ?? $name,
                'source' => 'local_registry',
            ]);
        })->values()->toArray();
    }

    /**
     * Get a global setting value
     */
    public function getGlobalSetting(string $key, mixed $default = null): mixed
    {
        $this->ensureStorageInitialized();

        return $this->storage->getSetting($key, $default);
    }

    /**
     * Update a global setting value
     */
    public function updateGlobalSetting(string $key, mixed $value): void
    {
        $this->ensureStorageInitialized();
        $this->storage->setSetting($key, $value);
    }

    /**
     * Get all global settings
     */
    public function getGlobalSettings(): array
    {
        $this->ensureStorageInitialized();

        return $this->storage->getAllSettings();
    }

    /**
     * Get registered service providers
     */
    public function getServiceProviders(): array
    {
        $this->ensureStorageInitialized();

        return $this->storage->getServiceProviders();
    }

    /**
     * Migrate existing config data to database storage
     */
    public function migrateFromConfig(): array
    {
        return $this->storage->migrateFromConfig();
    }
}
