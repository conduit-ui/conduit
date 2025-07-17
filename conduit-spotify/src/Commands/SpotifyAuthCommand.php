<?php

namespace JordanPartridge\ConduitSpotify\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;

class SpotifyAuthCommand extends Command
{
    protected $signature = 'spotify:auth 
                           {--logout : Logout from Spotify}
                           {--status : Show authentication status}
                           {--debug : Show debug information}';

    protected $description = 'Authenticate with Spotify or manage authentication';

    public function handle(SpotifyAuthInterface $auth): int
    {
        if ($this->option('logout')) {
            return $this->handleLogout($auth);
        }

        if ($this->option('status')) {
            return $this->handleStatus($auth);
        }

        if ($this->option('debug')) {
            return $this->handleDebug();
        }

        return $this->handleAuthentication($auth);
    }

    private function handleLogout(SpotifyAuthInterface $auth): int
    {
        if (! $auth->isAuthenticated()) {
            $this->info('❌ Not currently authenticated');

            return 0;
        }

        $auth->revoke();
        $this->info('✅ Logged out from Spotify');

        return 0;
    }

    private function handleStatus(SpotifyAuthInterface $auth): int
    {
        if ($auth->isAuthenticated()) {
            $this->info('✅ Authenticated with Spotify');
            $this->line('   Token is valid and ready to use');
        } else {
            $this->info('❌ Not authenticated with Spotify');
            $this->line('   Run: php conduit spotify:auth');
        }

        return 0;
    }

    private function handleAuthentication(SpotifyAuthInterface $auth): int
    {
        if ($auth->isAuthenticated()) {
            $this->info('✅ Already authenticated with Spotify');

            return 0;
        }

        // Check stored credentials first, fallback to config
        $fileCache = Cache::store('file');
        $clientId = $fileCache->get('spotify_client_id') ?: config('spotify.client_id');
        $clientSecret = $fileCache->get('spotify_client_secret') ?: config('spotify.client_secret');

        if (! $clientId || ! $clientSecret) {
            $this->error('❌ Spotify not configured');
            $this->newLine();
            $this->line('<options=bold>Quick Setup:</options>');
            $this->line('   Run: <comment>php conduit spotify:setup</comment>');
            $this->newLine();
            $this->line('This will guide you through creating a Spotify app and storing credentials.');
            $this->newLine();

            return 1;
        }

        try {
            $this->info('🌐 Starting temporary authentication server...');
            $this->line('   This will automatically handle the OAuth callback');
            $this->newLine();

            $this->info('🔗 Opening Spotify authorization in your browser...');

            // Use the enhanced local server flow
            $tokenData = $auth->authenticateWithLocalServer();

            $this->info('✅ Successfully authenticated with Spotify!');
            $this->line('   Authentication server stopped');
            $this->line('   You can now use Spotify commands');
            $this->newLine();
            $this->line('💡 Try: php conduit spotify:current');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Authentication failed: {$e->getMessage()}");

            // Fallback instructions
            if (str_contains($e->getMessage(), 'Port') || str_contains($e->getMessage(), 'not available')) {
                $this->newLine();
                $this->line('<options=bold>Alternative:</options> Use manual authentication');
                $this->line('Run: php conduit spotify:auth --manual');
            }

            return 1;
        }
    }

    private function handleDebug(): int
    {
        $this->line('<options=bold>Spotify Debug Information:</options>');
        $this->newLine();

        // Check stored credentials
        $fileCache = Cache::store('file');
        $storedClientId = $fileCache->get('spotify_client_id');
        $storedClientSecret = $fileCache->get('spotify_client_secret');

        // Check config credentials
        $configClientId = config('spotify.client_id');
        $configClientSecret = config('spotify.client_secret');

        $this->line('Stored Credentials:');
        $this->line('  Client ID: '.($storedClientId ? '✅ SET ('.substr($storedClientId, 0, 8).'...)' : '❌ NOT SET'));
        $this->line('  Client Secret: '.($storedClientSecret ? '✅ SET ('.substr($storedClientSecret, 0, 8).'...)' : '❌ NOT SET'));

        $this->newLine();
        $this->line('Config Credentials:');
        $this->line('  Client ID: '.($configClientId ? '✅ SET ('.substr($configClientId, 0, 8).'...)' : '❌ NOT SET'));
        $this->line('  Client Secret: '.($configClientSecret ? '✅ SET ('.substr($configClientSecret, 0, 8).'...)' : '❌ NOT SET'));

        $this->newLine();
        $this->line('Authentication tokens:');
        $accessToken = $fileCache->get('spotify_access_token');
        $refreshToken = $fileCache->get('spotify_refresh_token');
        $this->line('  Access Token: '.($accessToken ? '✅ SET' : '❌ NOT SET'));
        $this->line('  Refresh Token: '.($refreshToken ? '✅ SET' : '❌ NOT SET'));

        return 0;
    }

    private function openBrowser(string $url): void
    {
        $os = PHP_OS_FAMILY;

        try {
            switch ($os) {
                case 'Darwin': // macOS
                    exec("open '{$url}'");
                    break;
                case 'Windows':
                    exec("start '{$url}'");
                    break;
                case 'Linux':
                    exec("xdg-open '{$url}'");
                    break;
            }
        } catch (\Exception $e) {
            // Silently fail if we can't open browser
        }
    }
}
