<?php

declare(strict_types=1);

namespace App\Actions;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Collection;

/**
 * Action to check for component updates via GitHub API
 */
class CheckComponentUpdates
{
    private Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client(['timeout' => 2]);
    }

    /**
     * Check for updates for given components
     */
    public function execute(array $components): Collection
    {
        $promises = [];
        $updates = collect();

        try {
            // Create parallel promises for all components
            foreach ($components as $name => $config) {
                $package = $config['package'] ?? null;
                if (!$package) continue;

                $promises[$name] = $this->httpClient->getAsync(
                    "https://api.github.com/repos/{$package}/releases/latest"
                );
            }

            // Resolve all promises with timeout
            $responses = Utils::settle($promises)->wait();

            foreach ($responses as $name => $response) {
                if ($response['state'] === 'fulfilled') {
                    $release = json_decode($response['value']->getBody()->getContents(), true);
                    $latest = $release['tag_name'] ?? null;
                    $current = $components[$name]['version'] ?? 'unknown';

                    if ($latest && $this->isNewerVersion($current, $latest)) {
                        $updates->put($name, [
                            'name' => $name,
                            'current' => $current,
                            'latest' => $latest,
                            'release_data' => $release,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Fail gracefully - never break commands
            error_log("Component update check failed: " . $e->getMessage());
        }

        return $updates;
    }

    /**
     * Check if current version is older than latest
     */
    private function isNewerVersion(string $current, string $latest): bool
    {
        // Remove 'v' prefix for comparison
        $current = ltrim($current, 'v');
        $latest = ltrim($latest, 'v');
        
        return version_compare($current, $latest, '<');
    }
}