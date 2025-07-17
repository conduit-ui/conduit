<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class GithubAuthService
{
    /**
     * Get GitHub token with fallback strategies.
     */
    public function getToken(): ?string
    {
        // Strategy 1: Environment variable
        $envToken = env('GITHUB_TOKEN');
        if (! empty($envToken)) {
            return $envToken;
        }

        // Strategy 2: GitHub CLI authentication
        $ghToken = $this->getGitHubCliToken();
        if (! empty($ghToken)) {
            return $ghToken;
        }

        return null;
    }

    /**
     * Get token from GitHub CLI if authenticated.
     */
    private function getGitHubCliToken(): ?string
    {
        try {
            // Check if gh CLI is installed and authenticated
            $process = new Process(['gh', 'auth', 'token']);
            $process->run();

            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
        } catch (\Exception $e) {
            // GitHub CLI not available or not authenticated
        }

        return null;
    }

    /**
     * Check if GitHub authentication is available.
     */
    public function isAuthenticated(): bool
    {
        return ! empty($this->getToken());
    }

    /**
     * Get authentication status for display.
     */
    public function getAuthStatus(): array
    {
        $envToken = env('GITHUB_TOKEN');
        $ghToken = $this->getGitHubCliToken();

        return [
            'authenticated' => $this->isAuthenticated(),
            'env_token' => ! empty($envToken),
            'gh_cli' => ! empty($ghToken),
            'method' => ! empty($envToken) ? 'environment' : (! empty($ghToken) ? 'github-cli' : 'none'),
        ];
    }
}
