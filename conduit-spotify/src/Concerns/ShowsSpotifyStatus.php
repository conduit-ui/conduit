<?php

namespace Conduit\Spotify\Concerns;

trait ShowsSpotifyStatus
{
    /**
     * Show current Spotify status bar with luxury vibes.
     */
    protected function showSpotifyStatusBar(): void
    {
        try {
            $api = app(\Conduit\Spotify\Contracts\ApiInterface::class);
            $current = $api->getCurrentTrack();
            
            if ($current && isset($current['item'])) {
                $track = $current['item'];
                $artist = collect($track['artists'])->pluck('name')->join(', ');
                $isPlaying = $current['is_playing'] ?? false;
                $status = $isPlaying ? '▶️' : '⏸️';
                
                $this->line('');
                $this->line("┌─ 🎵 <fg=magenta;options=bold>Spotify Status</fg=magenta;options=bold> ─────────────");
                $this->line("│ {$status} <fg=cyan>{$track['name']}</fg=cyan>");
                $this->line("│ 🎤 <fg=yellow>{$artist}</fg=yellow>");
                $this->line("└────────────────────────────────");
                $this->line('');
            }
        } catch (\Exception $e) {
            // Silently ignore if we can't get status - don't break the command
        }
    }
}