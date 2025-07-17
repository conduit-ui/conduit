<?php

namespace JordanPartridge\ConduitSpotify\Commands;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;

class SpotifySkipCommand extends Command
{
    protected $signature = 'spotify:skip 
                           {--previous : Skip to previous track instead of next}
                           {--device= : Device ID to control}';

    protected $description = 'Skip to next or previous track';

    public function handle(SpotifyAuthInterface $auth, SpotifyApiInterface $api): int
    {
        if (!$auth->isAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:auth');
            return 1;
        }

        try {
            $deviceId = $this->option('device');
            $previous = $this->option('previous');

            if ($previous) {
                $success = $api->skipToPrevious($deviceId);
                $action = 'previous';
                $emoji = '⏮️';
            } else {
                $success = $api->skipToNext($deviceId);
                $action = 'next';
                $emoji = '⏭️';
            }

            if ($success) {
                $this->info("{$emoji} Skipped to {$action} track");
                
                // Show new current track after a moment
                sleep(1);
                $current = $api->getCurrentTrack();
                if ($current && isset($current['item'])) {
                    $track = $current['item'];
                    $artist = collect($track['artists'])->pluck('name')->join(', ');
                    $this->line("🎵 <info>{$track['name']}</info> by <comment>{$artist}</comment>");
                }

                return 0;
            } else {
                $this->error("❌ Failed to skip to {$action} track");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }
}