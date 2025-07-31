<?php

declare(strict_types=1);

namespace App\Policies;

use App\Services\JsonComponentRegistrar;

/**
 * Policy to determine when component update checks should run
 */
class UpdateCheckPolicy
{
    private JsonComponentRegistrar $registrar;

    public function __construct(JsonComponentRegistrar $registrar)
    {
        $this->registrar = $registrar;
    }

    /**
     * Determine if update check should be performed
     */
    public function shouldCheck(): bool
    {
        return $this->isConsoleEnvironment()
            && ! $this->isAutomationMode()
            && ! $this->isTestEnvironment()
            && $this->hasComponents();
    }

    /**
     * Check if running in console environment
     */
    private function isConsoleEnvironment(): bool
    {
        return app()->runningInConsole();
    }

    /**
     * Check if running in automation/CI mode
     */
    private function isAutomationMode(): bool
    {
        // Check for CI environment variables
        $ciEnvs = ['CI', 'CONTINUOUS_INTEGRATION', 'GITHUB_ACTIONS', 'TRAVIS', 'CIRCLECI'];
        foreach ($ciEnvs as $env) {
            if (getenv($env)) {
                return true;
            }
        }

        // Check for no-interaction flag
        return app()->bound('command') && app('command')->option('no-interaction');
    }

    /**
     * Check if running in test environment
     */
    private function isTestEnvironment(): bool
    {
        return app()->runningUnitTests() || app()->environment('testing');
    }

    /**
     * Check if there are components to update
     */
    private function hasComponents(): bool
    {
        return ! empty($this->registrar->getRegisteredComponents());
    }

    /**
     * Check if user has disabled update checks
     */
    public function isUpdateCheckEnabled(): bool
    {
        return config('conduit.update_check.enabled', true);
    }

    /**
     * Get check interval preference
     */
    public function getCheckInterval(): string
    {
        return config('conduit.update_check.interval', '6h');
    }

    /**
     * Get timeout for update checks
     */
    public function getCheckTimeout(): int
    {
        return config('conduit.update_check.timeout', 2);
    }
}
