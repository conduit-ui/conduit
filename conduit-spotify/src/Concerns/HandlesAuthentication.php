<?php

namespace Conduit\Spotify\Concerns;

use Conduit\Spotify\Contracts\AuthInterface;

trait HandlesAuthentication
{
    protected function ensureAuthenticatedWithRetry(AuthInterface $auth): bool
    {
        if ($auth->ensureAuthenticated()) {
            return true;
        }

        $this->error('❌ Not authenticated with Spotify');

        // Ask if they want to login now
        if ($this->confirm('Would you like to login now?', true)) {
            $this->info('🔐 Starting Spotify login...');

            // Run the login command
            $loginResult = $this->call('spotify:login');

            if ($loginResult === 0) {
                $this->newLine();
                $this->info('✅ Login successful! Continuing...');
                $this->newLine();

                // Retry auth check
                if ($auth->ensureAuthenticated()) {
                    return true;
                }

                $this->error('❌ Authentication verification failed. Please try again.');
            } else {
                $this->error('❌ Login failed. Please try again.');
            }
        } else {
            $this->info('💡 Run: conduit spotify:login');
        }

        return false;
    }
}
