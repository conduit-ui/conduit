<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Concerns\ShowsSpotifyStatus;
use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;

class Next extends Command
{
    use ShowsSpotifyStatus;

    protected $signature = 'spotify:next 
                           {--device= : Device ID to control}';

    protected $description = 'Skip to next track (alias for spotify:skip)';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:login');

            return 1;
        }

        try {
            $deviceId = $this->option('device');
            $success = $api->skipToNext($deviceId);

            if ($success) {
                $this->info('⏭️ Skipped to next track');

                // Show status bar after skip
                sleep(1);
                $this->showSpotifyStatusBar();

                return 0;
            } else {
                $this->error('❌ Failed to skip to next track');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }
}
