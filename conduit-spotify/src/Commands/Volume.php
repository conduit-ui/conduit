<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;

class Volume extends Command
{
    protected $signature = 'spotify:volume 
                           {level? : Volume level (0-100)}
                           {--device= : Device ID to control}
                           {--up : Increase volume by 10}
                           {--down : Decrease volume by 10}';

    protected $description = 'Control Spotify volume';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:login');

            return 1;
        }

        try {
            $level = $this->argument('level');
            $deviceId = $this->option('device');
            $up = $this->option('up');
            $down = $this->option('down');

            // Get current volume if we need to adjust relatively
            $currentVolume = 50; // Default fallback
            if ($up || $down) {
                $playback = $api->getCurrentPlayback();
                if ($playback && isset($playback['device']['volume_percent'])) {
                    $currentVolume = $playback['device']['volume_percent'];
                }
            }

            // Calculate target volume
            if ($up) {
                $targetVolume = min(100, $currentVolume + 10);
            } elseif ($down) {
                $targetVolume = max(0, $currentVolume - 10);
            } elseif ($level !== null) {
                $targetVolume = max(0, min(100, (int) $level));
            } else {
                // Just show current volume
                $playback = $api->getCurrentPlayback();
                if ($playback && isset($playback['device']['volume_percent'])) {
                    $volume = $playback['device']['volume_percent'];
                    $device = $playback['device']['name'] ?? 'Unknown Device';
                    $this->info("🔊 Volume: {$volume}% on {$device}");
                } else {
                    $this->info('🔇 No active playback device found');
                }

                return 0;
            }

            // Set the volume
            $success = $api->setVolume($targetVolume, $deviceId);

            if ($success) {
                $emoji = match (true) {
                    $targetVolume == 0 => '🔇',
                    $targetVolume < 30 => '🔈',
                    $targetVolume < 70 => '🔉',
                    default => '🔊'
                };

                $this->info("{$emoji} Volume set to {$targetVolume}%");

                return 0;
            } else {
                $this->error('❌ Failed to set volume');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }
}
