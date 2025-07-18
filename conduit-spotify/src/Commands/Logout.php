<?php

namespace Conduit\Spotify\Commands;

use Illuminate\Console\Command;
use Conduit\Spotify\Contracts\AuthInterface;

class Logout extends Command
{
    protected $signature = 'spotify:logout';

    protected $description = 'Logout from Spotify';

    public function handle(AuthInterface $auth): int
    {
        if (!$auth->isAuthenticated()) {
            $this->info('❌ Not currently logged in to Spotify');
            return 0;
        }

        $auth->revoke();
        $this->info('✅ Logged out from Spotify');

        return 0;
    }
}