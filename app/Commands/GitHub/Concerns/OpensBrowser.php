<?php

namespace App\Commands\GitHub\Concerns;

trait OpensBrowser
{
    /**
     * Open URL in default browser
     */
    protected function openInBrowser(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => "open '{$url}'",
            'Windows' => "start '{$url}'",
            'Linux' => "xdg-open '{$url}'",
            default => null,
        };

        if ($command) {
            exec($command . ' 2>/dev/null &');
            $this->info('🌐 Opening in browser...');
        } else {
            $this->warn('⚠️ Could not detect browser command for your OS');
            $this->line("🔗 Manual link: {$url}");
        }
    }
}