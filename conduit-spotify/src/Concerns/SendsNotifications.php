<?php

namespace Conduit\Spotify\Concerns;

use Illuminate\Support\Facades\Process;

trait SendsNotifications
{
    /**
     * Send a desktop notification using Laravel Zero's built-in system
     */
    protected function sendNotification(string $title, string $message, ?string $sound = 'default', ?string $url = null): void
    {
        try {
            // Use Laravel Zero's built-in notification system with Spotify icon
            $this->notify($title, $message, '/tmp/spotify_logo.png');

            // If there's a URL, open it after notification
            if ($url) {
                Process::run(['open', $url]);
            }
        } catch (\Exception $e) {
            // Silently fail - notifications are not critical
        }
    }

    /**
     * Send a gorgeous notification about currently playing track
     */
    protected function notifyNowPlaying(array $trackInfo): void
    {
        if (! isset($trackInfo['name']) || ! isset($trackInfo['artists'])) {
            return;
        }

        $artist = $trackInfo['artists'][0]['name'] ?? 'Unknown Artist';
        $trackName = $trackInfo['name'];

        // Clean, sophisticated notification design
        $title = 'Conduit';

        // Elegant, minimal message
        $message = "Playing \"{$trackName}\" by {$artist} on Spotify";

        // Include Spotify URL if available
        $url = isset($trackInfo['external_urls']['spotify'])
            ? $trackInfo['external_urls']['spotify']
            : null;

        // Use a more delightful sound
        $this->sendNotification($title, $message, 'Purr', $url);
    }

    /**
     * Send a clean notification about playback resuming
     */
    protected function notifyPlaybackResumed(): void
    {
        $this->sendNotification(
            'Conduit',
            'Resumed playback on Spotify',
            'Purr'
        );
    }
}
