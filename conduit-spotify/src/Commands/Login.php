<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class Login extends Command
{
    protected $signature = 'spotify:login 
                           {--status : Show authentication status}
                           {--debug : Show debug information}
                           {--manual : Show manual authentication help}';

    protected $description = 'Login to Spotify';

    public function handle(AuthInterface $auth): int
    {
        if ($this->option('status')) {
            return $this->handleStatus($auth);
        }

        if ($this->option('debug')) {
            return $this->handleDebug();
        }

        if ($this->option('manual')) {
            return $this->handleHelp();
        }

        return $this->handleAuthentication($auth);
    }

    private function handleHelp(): int
    {
        $this->line('<options=bold>🎵 Spotify Authentication Help</options>');
        $this->newLine();

        $this->line('<info>Basic Usage:</info>');
        $this->line('  conduit spotify:login              Login to Spotify (auto-opens browser)');
        $this->line('  conduit spotify:logout             Logout from Spotify');
        $this->line('  conduit spotify:login --status     Check login status');
        $this->newLine();

        $this->line('<info>Manual Authentication:</info>');
        $this->line('If automatic browser opening fails, you can:');
        $this->line('1. Copy the authorization URL from the login output');
        $this->line('2. Open it manually in any browser');
        $this->line('3. Complete the authorization');
        $this->line('4. The callback page will auto-close');
        $this->newLine();

        $this->line('<info>Troubleshooting:</info>');
        $this->line('• Use --debug to see credential status');
        $this->line('• Run "conduit spotify:setup" to configure credentials');
        $this->line('• Make sure port 9876 is available for authentication');

        return 0;
    }

    private function handleStatus(AuthInterface $auth): int
    {
        if ($auth->isAuthenticated()) {
            $this->info('✅ Authenticated with Spotify');
            $this->line('   Token is valid and ready to use');
        } else {
            $this->info('❌ Not authenticated with Spotify');
            $this->line('   Run: conduit spotify:login');
        }

        return 0;
    }

    private function handleAuthentication(AuthInterface $auth): int
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
            $this->line('   Run: <comment>conduit spotify:setup</comment>');
            $this->newLine();
            $this->line('This will guide you through creating a Spotify app and storing credentials.');
            $this->newLine();

            return 1;
        }

        try {
            if ($this->option('manual')) {
                return $this->handleManualAuthentication($auth);
            }

            // Use PHP built-in server auth with progress bars!
            return $this->handleServerAuth($auth);

        } catch (\Exception $e) {
            $this->error("❌ Authentication failed: {$e->getMessage()}");

            // Fallback instructions
            if (str_contains($e->getMessage(), 'Port') || str_contains($e->getMessage(), 'not available')) {
                $this->newLine();
                $this->line('<options=bold>Alternative:</options> Use manual authentication');
                $this->line('Run: conduit spotify:login --help');
            }

            return 1;
        }
    }

    private function handleDebug(): int
    {
        $this->line('<options=bold>Spotify Debug Information:</options>');
        $this->newLine();

        // Check stored credentials
        $storedClientId = $this->configService->getClientId();
        $storedClientSecret = $this->configService->getClientSecret();

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

    private function handleDeviceFlow(AuthInterface $auth): int
    {
        $this->info('🎵 Spotify Device Authorization');
        $this->line('   No browser needed - just visit a webpage!');
        $this->newLine();

        try {
            $deviceFlow = new \Conduit\Spotify\Services\SpotifyDeviceFlow;

            // Get device code
            $deviceInfo = $deviceFlow->authenticateDevice();
            $instructions = $deviceFlow->getAuthInstructions($deviceInfo);

            // Display beautiful instructions
            $this->displayDeviceInstructions($instructions);

            // Poll for completion
            $this->info('⏳ Waiting for authorization...');
            $tokenData = $deviceFlow->waitForDeviceAuth($deviceInfo);

            $this->newLine();
            $this->info('✅ Successfully authenticated with Spotify!');
            $this->line('   You can now use Spotify commands');
            $this->newLine();
            $this->line('💡 Try: php conduit spotify:current');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Device flow failed: {$e->getMessage()}");

            return 1;
        }
    }

    private function displayDeviceInstructions(array $instructions): void
    {
        $this->line('<options=bold>📱 Complete authorization on any device:</options>');
        $this->newLine();

        // Show the steps with emojis
        $this->line('1. 🌐 Visit: <comment>https://spotify.com/pair</comment>');
        $this->line('2. 🔑 Enter code: <info>'.$instructions['user_code'].'</info>');
        $this->line('3. ✅ Authorize Conduit');

        $this->newLine();
        $this->line('<fg=yellow>Code expires in '.($instructions['expires_in'] / 60).' minutes</fg>');
        $this->newLine();
    }

    private function handleSimpleManualAuth(AuthInterface $auth): int
    {
        $this->info('🔗 Spotify Authorization (No Browser)');
        $this->newLine();

        try {
            // Generate auth URL
            $authUrl = $auth->getAuthorizationUrl();

            $this->line('<options=bold>📋 Copy this URL and open it in any browser:</options>');
            $this->newLine();
            $this->line("<comment>{$authUrl}</comment>");
            $this->newLine();

            $this->line('After authorizing, copy the full callback URL and paste it here:');
            $callbackUrl = $this->ask('Callback URL');

            if (! $callbackUrl) {
                $this->error('❌ No callback URL provided');

                return 1;
            }

            // Extract code from URL
            $parsedUrl = parse_url($callbackUrl);
            if (! $parsedUrl || ! isset($parsedUrl['query'])) {
                $this->error('❌ Invalid callback URL');

                return 1;
            }

            parse_str($parsedUrl['query'], $queryParams);
            $code = $queryParams['code'] ?? null;

            if (! $code) {
                $this->error('❌ No authorization code found');

                return 1;
            }

            // Exchange for token
            $tokenData = $auth->exchangeCodeForToken($code);

            $this->info('✅ Successfully authenticated with Spotify!');
            $this->line('💡 Try: php conduit spotify:current');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Authentication failed: {$e->getMessage()}");

            return 1;
        }
    }

    private function handleSmartClipboardAuth(AuthInterface $auth): int
    {
        $this->info('🎵 Spotify Smart Authentication');
        $this->line('   Open URL in browser, copy callback URL - auto-detection enabled!');
        $this->newLine();

        try {
            $clipboardAuth = new \Conduit\Spotify\Services\SpotifyClipboardAuth;

            // Check if clipboard monitoring is supported
            if (! $clipboardAuth->isClipboardMonitoringSupported()) {
                $this->warn('⚠️  Clipboard monitoring not supported on this platform');

                return $this->handleSimpleManualAuth($auth);
            }

            // Generate auth URL
            $authUrl = $auth->getAuthorizationUrl();

            // Display clean URL
            $this->displayCleanAuthUrl($authUrl);

            // Monitor clipboard
            $this->info('📋 Monitoring clipboard for authorization...');
            $this->line('   After authorizing, copy the callback URL from your browser');
            $this->newLine();

            $callbackUrl = $clipboardAuth->monitorClipboardForCallback(300); // 5 minutes

            if (! $callbackUrl) {
                $this->warn('⏰ Clipboard monitoring timed out');

                return $this->fallbackToManualPaste($auth);
            }

            // Extract code
            $code = $clipboardAuth->extractCodeFromUrl($callbackUrl);

            if (! $code) {
                $this->error('❌ Could not extract authorization code from URL');

                return $this->fallbackToManualPaste($auth);
            }

            // Complete authentication
            $tokenData = $auth->exchangeCodeForToken($code);

            $this->newLine();
            $this->info('✅ Authentication completed automatically!');
            $this->line('🎵 Spotify is ready to rock!');
            $this->newLine();
            $this->line('💡 Try: php conduit spotify:current');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Smart auth failed: {$e->getMessage()}");

            return $this->fallbackToManualPaste($auth);
        }
    }

    private function displayCleanAuthUrl(string $url): void
    {
        $this->line('<options=bold>🌐 Open this URL in your browser:</options>');
        $this->newLine();
        $this->line("<comment>{$url}</comment>");
        $this->newLine();
        $this->line('<options=bold>📋 Then copy the callback URL from your browser address bar</options>');
        $this->line('   (The URL will start with http://127.0.0.1:9876/callback?code=...)');
        $this->newLine();
    }

    private function fallbackToManualPaste(AuthInterface $auth): int
    {
        $this->info('🔗 Manual Authentication');
        $this->newLine();

        try {
            $authUrl = $auth->getAuthorizationUrl();

            $this->line('If the URL is not in your clipboard, copy this:');
            $this->line("<comment>{$authUrl}</comment>");
            $this->newLine();

            $callbackUrl = $this->ask('Paste the callback URL here');

            if (! $callbackUrl) {
                $this->error('❌ No callback URL provided');

                return 1;
            }

            $clipboardAuth = new \Conduit\Spotify\Services\SpotifyClipboardAuth;
            $code = $clipboardAuth->extractCodeFromUrl($callbackUrl);

            if (! $code) {
                $this->error('❌ Could not extract authorization code');

                return 1;
            }

            $tokenData = $auth->exchangeCodeForToken($code);

            $this->info('✅ Authentication completed!');
            $this->line('💡 Try: php conduit spotify:current');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Authentication failed: {$e->getMessage()}");

            return 1;
        }
    }

    private function handleServerAuth(AuthInterface $auth): int
    {
        $this->info('🎵 Spotify Authentication');
        $this->line('   Starting secure authentication flow...');
        $this->newLine();

        try {
            $server = new \Conduit\Spotify\Services\BuiltinServer;
            $authUrl = null;
            $authCode = null;

            // Task 1: Start temporary server
            $this->task('Starting temporary server', function () use ($server) {
                return $server->startServer();
            });

            // Task 2: Generate auth URL
            $this->task('Generating authorization URL', function () use ($auth, &$authUrl) {
                $authUrl = $auth->getAuthorizationUrl();

                return ! empty($authUrl);
            });

            // Display the auth URL and open browser
            $this->newLine();
            $this->line('<options=bold>🌐 Opening browser for authorization...</options>');
            $this->openBrowser($authUrl);
            $this->line('   <comment>Browser opened with authorization URL</comment>');
            $this->newLine();

            // Task 3: Wait for user authorization
            $this->task('Waiting for authorization', function () use ($server, &$authCode) {
                $authCode = $this->waitForAuthCode($server, 300); // 5 minutes

                return ! empty($authCode);
            });

            // Task 4: Complete authentication
            $this->task('Processing authentication', function () use ($auth, $authCode) {
                $tokenData = $auth->exchangeCodeForToken($authCode);

                return ! empty($tokenData['access_token'] ?? null);
            });

            // Task 5: Cleanup
            $this->task('Stopping server', function () use ($server) {
                $server->stopServer();
                $this->cleanupTempFiles();

                return true;
            });

            $this->newLine();
            $this->info('✅ Authentication completed successfully!');
            $this->line('🎵 Spotify is ready to rock!');
            $this->newLine();
            $this->line('💡 Try: php conduit spotify:current');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Authentication failed: {$e->getMessage()}");

            // Cleanup on error
            if (isset($server)) {
                $server->stopServer();
                $this->cleanupTempFiles();
            }

            return 1;
        }
    }

    private function waitForAuthCode($server, int $timeoutSeconds): ?string
    {
        $startTime = time();
        $authCodeFile = '/tmp/spotify_auth_code';
        $authErrorFile = '/tmp/spotify_auth_error';

        while ((time() - $startTime) < $timeoutSeconds) {
            // Check for auth code
            if (file_exists($authCodeFile)) {
                $authData = json_decode(file_get_contents($authCodeFile), true);

                return $authData['code'] ?? null;
            }

            // Check for auth error
            if (file_exists($authErrorFile)) {
                $error = file_get_contents($authErrorFile);
                throw new \Exception("Auth error: {$error}");
            }

            // Check if server is still running
            if (! $server->isRunning()) {
                throw new \Exception('Server stopped unexpectedly');
            }

            usleep(500000); // Check every 500ms
        }

        throw new \Exception("Auth timeout after {$timeoutSeconds} seconds");
    }

    private function cleanupTempFiles(): void
    {
        @unlink('/tmp/spotify_auth_code');
        @unlink('/tmp/spotify_auth_error');
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
