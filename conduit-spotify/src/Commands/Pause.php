<?php

namespace Conduit\Spotify\Commands;

use Illuminate\Console\Command;
use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;

class Pause extends Command
{
    protected $signature = 'spotify:pause {--device= : Device ID to pause}';

    protected $description = 'Pause Spotify playback';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:login');

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
