<?php

declare(strict_types=1);

namespace App\Actions;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Action to handle caching of update check results
 */
class CacheUpdateResults
{
    private string $cacheFile;

    public function __construct()
    {
        $this->cacheFile = config_path('update-cache.json');
    }

    /**
     * Save update results to cache
     */
    public function save(Collection $updates): void
    {
        $cache = [
            'last_check' => Carbon::now()->toISOString(),
            'updates' => $updates->toArray(),
        ];

        file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }

    /**
     * Load cached update results
     */
    public function load(): array
    {
        if (! file_exists($this->cacheFile)) {
            return [];
        }

        $content = file_get_contents($this->cacheFile);

        return json_decode($content, true) ?: [];
    }

    /**
     * Check if cached results are expired
     */
    public function isExpired(): bool
    {
        $cache = $this->load();
        $lastCheck = $cache['last_check'] ?? null;

        if (! $lastCheck) {
            return true;
        }

        $interval = config('conduit.update_check.interval', '6h');
        $expiry = Carbon::parse($lastCheck)->add($this->parseInterval($interval));

        return Carbon::now()->isAfter($expiry);
    }

    /**
     * Get cached updates if not expired
     */
    public function getCachedUpdates(): Collection
    {
        if ($this->isExpired()) {
            return collect();
        }

        $cache = $this->load();

        return collect($cache['updates'] ?? []);
    }

    /**
     * Parse interval string to DateInterval
     */
    private function parseInterval(string $interval): \DateInterval
    {
        $matches = [];
        if (preg_match('/^(\d+)([hd])$/', $interval, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            return match ($unit) {
                'h' => \DateInterval::createFromDateString("{$value} hours"),
                'd' => \DateInterval::createFromDateString("{$value} days"),
                default => \DateInterval::createFromDateString('6 hours')
            };
        }

        return \DateInterval::createFromDateString('6 hours');
    }
}
