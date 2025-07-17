<?php

namespace JordanPartridge\ConduitSpotify\Commands;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;

class SpotifyPauseCommand extends Command
{
    protected $signature = 'spotify:pause {--device= : Device ID to pause}';

    protected $description = 'Pause Spotify playback';

    public function handle(SpotifyAuthInterface $auth, SpotifyApiInterface $api): int
    {
        if (!$auth->isAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:auth');
            return 1;
        }

        try {
            $deviceId = $this->option('device');
            
            $success = $api->pause($deviceId);

            if ($success) {
                $this->info('⏸️  Playback paused');
                return 0;
            } else {
                $this->error('❌ Failed to pause playback');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }
}