<?php

namespace Conduit\Spotify\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class Setup extends Command
{
    protected $signature = 'spotify:setup 
                           {--reset : Reset existing credentials}';

    protected $description = 'Set up Spotify integration with beautiful guided setup';

    public function handle(): int
    {
        if ($this->option('reset')) {
            return $this->handleReset();
        }

        return $this->handleSetup();
    }

    private function handleReset(): int
    {
        if (! confirm('This will remove your stored Spotify credentials. Continue?')) {
            info('Setup cancelled.');

            return 0;
        }

        $this->clearStoredCredentials();
        info('✅ Spotify credentials cleared');
        note('Run: php conduit spotify:setup');

        return 0;
    }

    private function handleSetup(): int
    {
        // Check if already configured
        if ($this->hasStoredCredentials() && ! $this->option('reset')) {
            info('✅ Spotify is already configured');
            note('Run: php conduit spotify:login (if not authenticated)');
            note('Run: php conduit spotify:setup --reset (to reconfigure)');

            return 0;
        }

        $this->displayWelcome();

        if (! confirm('Ready to set up Spotify integration?', true)) {
            info('Setup cancelled.');

            return 0;
        }

        return $this->executeSetupTasks();
    }

    private function executeSetupTasks(): int
    {
        $this->newLine();
        $this->line('🎵 <options=bold>Setting up Spotify Integration</options>');
        $this->newLine();

        $appUrl = null;
        $credentials = null;
        $defaultPort = (int) ($_ENV['SPOTIFY_CALLBACK_PORT'] ?? 9876);

        try {
            // Task 1: Determine callback port
            $this->task('Determining callback port', function () use ($defaultPort) {
                // Test if default port is available
                $connection = @fsockopen('127.0.0.1', $defaultPort, $errno, $errstr, 2);
                if ($connection) {
                    fclose($connection);

                    return false; // Port is in use
                }

                return true; // Port is available
            });

            // Task 2: Open Spotify Developer Dashboard
            $this->task('Opening Spotify Developer Dashboard', function () use (&$appUrl) {
                $appUrl = 'https://developer.spotify.com/dashboard/applications';
                $this->openBrowser($appUrl);

                return true;
            });

            // Task 3: Display app configuration
            $this->task('Preparing app configuration', function () use ($defaultPort) {
                $this->newLine();
                $this->displayAppConfiguration($defaultPort);

                return true;
            });

            // Task 4: Wait for app creation
            $this->task('Waiting for app creation', function () use ($defaultPort) {
                $redirectUri = "http://127.0.0.1:{$defaultPort}/callback";

                info('📋 Now create your Spotify app in the browser');
                note('Follow the 6 steps shown above');

                $this->newLine();
                $this->line('<fg=cyan;options=bold>📋 Quick Copy: Redirect URI</fg=cyan;options=bold>');
                $this->line("<fg=green;options=bold>   {$redirectUri}</fg=green;options=bold>");
                $this->newLine();

                // Try to copy to clipboard
                if ($this->copyToClipboard($redirectUri)) {
                    note('✅ Redirect URI copied to clipboard!');
                } else {
                    note('💡 Tip: Triple-click the green URL above to select it easily');
                }

                return confirm('✅ Have you created the app and are viewing its settings/dashboard page?', true);
            });

            // Task 5: Collect credentials
            $this->task('Collecting app credentials', function () use (&$credentials) {
                $credentials = $this->collectCredentials();

                return $credentials !== null;
            });

            // Task 6: Validate credentials
            $this->task('Validating credentials', function () use ($credentials) {
                return $this->validateCredentials($credentials);
            });

            // Task 7: Store credentials
            $this->task('Storing credentials securely', function () use ($credentials) {
                $this->storeCredentials($credentials);

                return $this->hasStoredCredentials();
            });

            // Task 8: Test connection
            $this->task('Testing Spotify connection', function () use ($credentials) {
                return spin(
                    fn () => $this->testSpotifyConnection($credentials),
                    'Validating credentials with Spotify API...'
                );
            });

            $this->newLine();
            $this->displaySuccess();

            // Offer to start authentication immediately
            if (confirm('🔐 Would you like to authenticate with Spotify now?', true)) {
                $this->newLine();
                info('🚀 Starting Spotify authentication...');

                return $this->call('spotify:login');
            }

            return 0;

        } catch (\Exception $e) {
            error("❌ Setup failed: {$e->getMessage()}");
            note('You can try running the setup again');

            return 1;
        }
    }

    private function displayWelcome(): void
    {
        info('🎵 Spotify Integration Setup');
        note('This will guide you through setting up your personal Spotify integration.');
        note('You\'ll need to create a Spotify app (takes 2 minutes).');
    }

    private function storeCredentials(array $credentials): void
    {
        // Use file cache store to ensure persistence across command runs
        $fileCache = Cache::store('file');

        // Store credentials with long TTL (30 days)
        $fileCache->put('spotify_client_id', $credentials['client_id'], now()->addDays(30));
        $fileCache->put('spotify_client_secret', $credentials['client_secret'], now()->addDays(30));

        // Verify storage worked
        $storedId = $fileCache->get('spotify_client_id');
        $storedSecret = $fileCache->get('spotify_client_secret');

        if (! $storedId || ! $storedSecret) {
            $this->error('❌ Failed to store credentials');
            throw new \Exception('File storage failed');
        }
    }

    private function displaySuccess(): void
    {
        info('🎉 Spotify integration setup complete!');

        note('🚀 What\'s next?');
        note('1. 🔐 php conduit spotify:login (authenticate with Spotify)');
        note('2. 🎵 php conduit spotify:current (see what\'s playing)');
        note('3. 🎧 php conduit spotify:focus coding (start coding music)');
        note('4. 📊 php conduit spotify:generate-playlists (create intelligent playlists)');

        note('💡 Pro Tips:');
        note('• Use SPOTIFY_CALLBACK_PORT environment variable to customize OAuth port');
        note('• Run php conduit spotify:setup --reset to reconfigure credentials');
        note('• All commands support --help for detailed usage information');
    }

    private function hasStoredCredentials(): bool
    {
        $fileCache = Cache::store('file');

        return $fileCache->has('spotify_client_id') && $fileCache->has('spotify_client_secret');
    }

    private function clearStoredCredentials(): void
    {
        $fileCache = Cache::store('file');
        $fileCache->forget('spotify_client_id');
        $fileCache->forget('spotify_client_secret');

        // Also clear any stored tokens
        $fileCache->forget('spotify_access_token');
        $fileCache->forget('spotify_refresh_token');
        $fileCache->forget('spotify_token_expires_at');
    }

    private function displayAppConfiguration(int $port): void
    {
        $username = $this->getSystemUsername();
        $appName = "Conduit CLI - {$username}";
        $redirectUri = "http://127.0.0.1:{$port}/callback";

        info('📋 Step-by-step Spotify app creation:');

        $this->newLine();
        note('1. 📱 App Name: Enter any name you prefer (suggestion: "'.$appName.'")');
        note('2. 📝 App Description: Enter any description (suggestion: "Personal music control for development workflows")');
        note('3. 🌐 Website URL: Enter any URL (suggestion: "https://github.com/jordanpartridge/conduit")');

        $this->newLine();
        $this->line('<fg=yellow;options=bold>4. 🔗 REDIRECT URI - COPY THIS EXACTLY:</fg=yellow;options=bold>');
        $this->line("<fg=green;options=bold>   {$redirectUri}</fg=green;options=bold>");
        $this->newLine();

        note('5. 📡 Which APIs: Select "Web API" (✅ Web API only - no Web Playback SDK needed)');
        note('6. ✅ Accept Terms of Service and click "Save"');

        $this->newLine();
        warning('⚠️  IMPORTANT: Must use 127.0.0.1 (not localhost) for security');
        note("💡 Using port {$port} for OAuth callback server");
        note('🔧 Set SPOTIFY_CALLBACK_PORT environment variable to customize port');
    }

    private function collectCredentials(): ?array
    {
        info('🔑 App Credentials');
        note('In your Spotify app dashboard:');
        note('1. Copy your Client ID (visible by default)');
        note('2. Click "View client secret" and copy the secret');

        $clientId = text(
            label: '📋 Client ID',
            placeholder: 'Paste your Spotify app Client ID',
            required: true,
            validate: fn (string $value) => strlen($value) < 20
                ? 'Client ID appears to be too short'
                : null
        );

        $clientSecret = password(
            label: '🔐 Client Secret',
            placeholder: 'Paste your Spotify app Client Secret',
            required: true,
            validate: fn (string $value) => strlen($value) < 20
                ? 'Client Secret appears to be too short'
                : null
        );

        return [
            'client_id' => trim($clientId),
            'client_secret' => trim($clientSecret),
        ];
    }

    private function validateCredentials(array $credentials): bool
    {
        // Basic validation
        if (strlen($credentials['client_id']) < 20) {
            throw new \Exception('Client ID appears to be invalid (too short)');
        }

        if (strlen($credentials['client_secret']) < 20) {
            throw new \Exception('Client Secret appears to be invalid (too short)');
        }

        // Pattern validation
        if (! preg_match('/^[a-zA-Z0-9]+$/', $credentials['client_id'])) {
            throw new \Exception('Client ID contains invalid characters');
        }

        if (! preg_match('/^[a-zA-Z0-9]+$/', $credentials['client_secret'])) {
            throw new \Exception('Client Secret contains invalid characters');
        }

        return true;
    }

    private function testSpotifyConnection(array $credentials): bool
    {
        try {
            // Test client credentials flow (doesn't require user auth)
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://accounts.spotify.com/api/token',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic '.base64_encode($credentials['client_id'].':'.$credentials['client_secret']),
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);

                return isset($data['access_token']);
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getSystemUsername(): string
    {
        return trim(shell_exec('whoami')) ?: 'Developer';
    }

    private function openBrowser(string $url): void
    {
        $os = PHP_OS_FAMILY;

        try {
            switch ($os) {
                case 'Darwin': // macOS
                    shell_exec("open '{$url}' > /dev/null 2>&1");
                    break;
                case 'Windows':
                    shell_exec("start '{$url}' > /dev/null 2>&1");
                    break;
                case 'Linux':
                    shell_exec("xdg-open '{$url}' > /dev/null 2>&1");
                    break;
            }
        } catch (\Exception $e) {
            $this->line("   Manual: {$url}");
        }
    }

    private function copyToClipboard(string $text): bool
    {
        $os = PHP_OS_FAMILY;

        try {
            switch ($os) {
                case 'Darwin': // macOS
                    shell_exec("echo '{$text}' | pbcopy");

                    return true;
                case 'Windows':
                    shell_exec("echo {$text} | clip");

                    return true;
                case 'Linux':
                    shell_exec("echo '{$text}' | xclip -selection clipboard");

                    return true;
            }
        } catch (\Exception $e) {
            // Clipboard copy failed
        }

        return false;
    }
}
