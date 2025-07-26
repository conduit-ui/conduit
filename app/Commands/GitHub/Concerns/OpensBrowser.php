<?php

namespace App\Commands\GitHub\Concerns;

trait OpensBrowser
{
    /**
     * Open URL in default browser
     */
    protected function openInBrowser(string $url): void
    {
        // Properly escape the URL to prevent command injection
        $escapedUrl = escapeshellarg($url);
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => "open {$escapedUrl}",
            'Windows' => "start {$escapedUrl}",
            'Linux' => "xdg-open {$escapedUrl}",
            default => null,
        };

        if ($command) {
            exec($command.' 2>/dev/null &');
            $this->info('🌐 Opening in browser...');
        } else {
            $this->warn('⚠️ Could not detect browser command for your OS');
            $this->line("🔗 Manual link: {$url}");
        }
    }
}
