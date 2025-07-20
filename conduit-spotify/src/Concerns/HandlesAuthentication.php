<?php

namespace Conduit\Spotify\Concerns;

use Conduit\Spotify\Contracts\AuthInterface;

trait HandlesAuthentication
{
    use ShowsSpotifyStatus;
    protected function ensureAuthenticatedWithRetry(AuthInterface $auth, int $maxAttempts = 3): bool
    {
        // First try the enhanced ensureAuthenticated method (with automatic retries)
        if ($auth->ensureAuthenticated()) {
            return true;
        }

        // If automatic retry failed, just fucking do the login for them!
        $this->info('🔐 Not authenticated. Let me handle that for you...');

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->info("🔄 Auto-login attempt {$attempt}/{$maxAttempts}...");

            // Just run the damn login command automatically
            $loginResult = $this->call('spotify:login');

            if ($loginResult === 0) {
                $this->info('✅ Auto-login successful!');

                // Verify it worked
                if ($auth->ensureAuthenticated()) {
                    $this->info('🎵 Ready to rock! Continuing...');
                    $this->showSpotifyStatusBar();
                    return true;
                }

                $this->warn('⚠️ Login succeeded but auth verification failed.');
            } else {
                $this->warn("⚠️ Auto-login attempt {$attempt} failed.");
            }

            // Small delay between attempts
            if ($attempt < $maxAttempts) {
                sleep(2);
            }
        }

        $this->error('❌ All auto-login attempts failed. You might need to manually run: conduit spotify:login');
        
        return false;
    }

    /**
     * Enhanced authentication check with automatic silent retries.
     */
    protected function ensureAuthenticatedSilent(AuthInterface $auth): bool
    {
        return $auth->ensureAuthenticated();
    }

}
