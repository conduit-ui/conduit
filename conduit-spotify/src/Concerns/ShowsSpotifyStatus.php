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
                
                // Get current playback for device/volume info
                $playback = $api->getCurrentPlayback();
                $volume = $playback['device']['volume_percent'] ?? null;
                $deviceName = $playback['device']['name'] ?? 'Unknown Device';
                
                $this->line('');
                $this->line("┌─ 🎵 <fg=magenta;options=bold>Spotify Status</fg=magenta;options=bold> ─────────────");
                $this->line("│ {$status} <fg=cyan>{$track['name']}</fg=cyan>");
                $this->line("│ 🎤 <fg=yellow>{$artist}</fg=yellow>");
                
                // Show volume bar if available
                if ($volume !== null) {
                    $volumeBar = $this->createVolumeBar($volume);
                    $this->line("│ 🔊 <fg=green>{$volume}%</fg=green> {$volumeBar}");
                }
                
                // Show device
                $this->line("│ 📱 <fg=gray>{$deviceName}</fg=gray>");
                $this->line("└────────────────────────────────");
                $this->line('');
            }
        } catch (\Exception $e) {
            // Silently ignore if we can't get status - don't break the command
        }
    }

    /**
     * Create a visual volume bar.
     */
    private function createVolumeBar(int $volume): string
    {
        $barLength = 10;
        $filled = (int) round(($volume / 100) * $barLength);
        $empty = $barLength - $filled;
        
        $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
        
        // Color based on volume level
        if ($volume > 70) {
            return "<fg=red>{$bar}</fg=red>";
        } elseif ($volume > 40) {
            return "<fg=yellow>{$bar}</fg=yellow>";
        } else {
            return "<fg=green>{$bar}</fg=green>";
        }
    }
}