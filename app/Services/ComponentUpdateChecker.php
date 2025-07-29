<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\CacheUpdateResults;
use App\Actions\CheckComponentUpdates;
use App\Actions\DetectUpdatePriority;
use App\Concerns\DisplaysUpdateStatus;
use App\Policies\UpdateCheckPolicy;
use Illuminate\Support\Collection;

/**
 * Orchestrates component update checking using Laravel Zero task separation
 */
class ComponentUpdateChecker
{
    use DisplaysUpdateStatus;

    private JsonComponentRegistrar $registrar;
    private UpdateCheckPolicy $policy;
    private CheckComponentUpdates $checker;
    private DetectUpdatePriority $priorityDetector;
    private CacheUpdateResults $cache;

    public function __construct(
        JsonComponentRegistrar $registrar,
        UpdateCheckPolicy $policy,
        CheckComponentUpdates $checker,
        DetectUpdatePriority $priorityDetector,
        CacheUpdateResults $cache
    ) {
        $this->registrar = $registrar;
        $this->policy = $policy;
        $this->checker = $checker;
        $this->priorityDetector = $priorityDetector;
        $this->cache = $cache;
    }

    /**
     * Check if we should perform update check
     */
    public function shouldCheck(): bool
    {
        return $this->policy->shouldCheck() 
            && $this->policy->isUpdateCheckEnabled()
            && $this->cache->isExpired();
    }

    /**
     * Perform quick update check for all components
     */
    public function quickCheck(): Collection
    {
        // Try cache first
        if (!$this->cache->isExpired()) {
            return $this->cache->getCachedUpdates();
        }

        $components = $this->registrar->getRegisteredComponents();
        $updates = $this->checker->execute($components);

        // Add priority detection to each update
        $updates = $updates->map(function ($update) {
            $update['priority'] = $this->priorityDetector->execute($update['release_data']);
            unset($update['release_data']); // Clean up raw data
            return $update;
        });

        // Cache the results
        $this->cache->save($updates);

        return $updates;
    }

    /**
     * Display update status to user using trait
     */
    public function displayUpdateStatus(): void
    {
        if (!$this->shouldCheck()) {
            return;
        }

        $updates = $this->quickCheck();
        $this->showUpdateStatus($updates);
    }
}