<?php

declare(strict_types=1);

namespace App\Actions;

/**
 * Action to detect update priority from release data
 */
class DetectUpdatePriority
{
    /**
     * Detect priority based on release information
     */
    public function execute(array $releaseData): string
    {
        $body = strtolower($releaseData['body'] ?? '');
        $name = strtolower($releaseData['name'] ?? '');
        $tagName = strtolower($releaseData['tag_name'] ?? '');

        // Check for security indicators
        if ($this->hasSecurityIndicators($body, $name)) {
            return 'security';
        }

        // Check for breaking changes
        if ($this->hasBreakingChanges($body, $name, $tagName)) {
            return 'breaking';
        }

        return 'normal';
    }

    /**
     * Check for security-related keywords
     */
    private function hasSecurityIndicators(string $body, string $name): bool
    {
        $securityKeywords = [
            'security', 'vulnerability', 'cve-', 'exploit', 'patch',
            'critical', 'urgent', 'hotfix', 'security fix',
        ];

        foreach ($securityKeywords as $keyword) {
            if (str_contains($body, $keyword) || str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for breaking change indicators
     */
    private function hasBreakingChanges(string $body, string $name, string $tagName): bool
    {
        $breakingKeywords = [
            'breaking', 'breaking change', 'incompatible', 'major version',
            'migration required', 'api change', 'removed',
        ];

        // Check for major version bump (e.g., v1.x.x -> v2.x.x)
        if (preg_match('/^v?(\d+)\./', $tagName, $matches)) {
            $majorVersion = (int) $matches[1];
            if ($majorVersion >= 2) {
                return true;
            }
        }

        foreach ($breakingKeywords as $keyword) {
            if (str_contains($body, $keyword) || str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
