<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Handles component update checking for Conduit
 * 
 * Runs on startup to check for component updates without blocking command execution
 */
class ComponentUpdateChecker
{
    private Client $httpClient;
    private JsonComponentRegistrar $registrar;
    private string $cacheFile;

    public function __construct(JsonComponentRegistrar $registrar)
    {
        $this->httpClient = new Client(['timeout' => 2]);
        $this->registrar = $registrar;
        $this->cacheFile = config_path('update-cache.json');
    }

    /**
     * Check if we should perform update check
     */
    public function shouldCheck(): bool
    {
        // Skip in automation/CI
        if ($this->isAutomationMode()) {
            return false;
        }

        // Skip if no components installed
        if (empty($this->registrar->getRegisteredComponents())) {
            return false;
        }

        // Check if enough time has passed
        return $this->lastCheckExpired();
    }

    /**
     * Perform quick update check for all components
     */
    public function quickCheck(): Collection
    {
        $components = $this->registrar->getRegisteredComponents();
        $cache = $this->loadCache();
        
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
                            'url' => $release['html_url'] ?? null,
                            'priority' => $this->detectPriority($release),
                        ]);
                    }
                }
            }

            // Update cache
            $this->saveCache($updates->toArray());

        } catch (\Exception $e) {
            // Fail gracefully - never break commands
            error_log("Component update check failed: " . $e->getMessage());
        }

        return $updates;
    }

    /**
     * Display update status to user
     */
    public function displayUpdateStatus(): void
    {
        if (!$this->shouldCheck()) {
            return;
        }

        $updates = $this->quickCheck();

        if ($updates->isEmpty()) {
            if ($this->isVerbose()) {
                echo "✅ All components up to date\n";
            }
            return;
        }

        echo "📦 " . $updates->count() . " component update(s) available:\n";
        
        foreach ($updates as $update) {
            $priority = $update['priority'] === 'security' ? ' (security)' : '';
            echo "  • {$update['name']} {$update['current']} → {$update['latest']}{$priority}\n";
        }
        
        echo "\n💡 Run 'conduit update' to install updates\n";
        echo str_repeat('─', 50) . "\n";
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

    /**
     * Detect update priority from release notes
     */
    private function detectPriority(array $release): string
    {
        $body = strtolower($release['body'] ?? '');
        $name = strtolower($release['name'] ?? '');
        
        if (str_contains($body, 'security') || str_contains($name, 'security')) {
            return 'security';
        }
        
        if (str_contains($body, 'breaking') || str_contains($name, 'breaking')) {
            return 'breaking';
        }
        
        return 'normal';
    }

    /**
     * Check if running in automation mode
     */
    private function isAutomationMode(): bool
    {
        return app()->runningInConsole() && 
               (app()->bound('command') && app('command')->option('no-interaction'));
    }

    /**
     * Check if last update check has expired
     */
    private function lastCheckExpired(): bool
    {
        $cache = $this->loadCache();
        $lastCheck = $cache['last_check'] ?? null;
        
        if (!$lastCheck) {
            return true;
        }
        
        $interval = config('conduit.update_check.interval', '6h');
        $expiry = Carbon::parse($lastCheck)->add($this->parseInterval($interval));
        
        return Carbon::now()->isAfter($expiry);
    }

    /**
     * Parse interval string to Carbon duration
     */
    private function parseInterval(string $interval): \DateInterval
    {
        $matches = [];
        if (preg_match('/^(\d+)([hd])$/', $interval, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            
            return match($unit) {
                'h' => \DateInterval::createFromDateString("{$value} hours"),
                'd' => \DateInterval::createFromDateString("{$value} days"),
                default => \DateInterval::createFromDateString('6 hours')
            };
        }
        
        return \DateInterval::createFromDateString('6 hours');
    }

    /**
     * Load update cache
     */
    private function loadCache(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }
        
        $content = file_get_contents($this->cacheFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Save update cache
     */
    private function saveCache(array $updates): void
    {
        $cache = [
            'last_check' => Carbon::now()->toISOString(),
            'updates' => $updates,
        ];
        
        file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }

    /**
     * Check if verbose output is enabled
     */
    private function isVerbose(): bool
    {
        return app()->bound('command') && app('command')->option('verbose');
    }
}