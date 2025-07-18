<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;

class Logout extends Command
{
    protected $signature = 'spotify:logout';

    protected $description = 'Logout from Spotify';

    public function handle(AuthInterface $auth): int
    {
        if (! $auth->isAuthenticated()) {
            $this->info('❌ Not currently logged in to Spotify');

            return 0;
        }

        $auth->revoke();
        $this->info('✅ Logged out from Spotify');

        return 0;
    }
}
