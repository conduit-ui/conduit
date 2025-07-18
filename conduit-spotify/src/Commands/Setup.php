<?php

namespace Conduit\Spotify\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class Setup extends Command
{
    protected $signature = 'spotify:setup 
                           {--reset : Reset existing credentials}';

    protected $description = 'Set up Spotify integration with guided app creation';

    public function handle(): int
    {
        if ($this->option('reset')) {
            return $this->handleReset();
        }

        return $this->handleSetup();
    }

    private function handleReset(): int
    {
        if (! $this->confirm('This will remove your stored Spotify credentials. Continue?')) {
            $this->info('Setup cancelled.');

            return 0;
        }

        $this->clearStoredCredentials();
        $this->info('✅ Spotify credentials cleared');
        $this->line('   Run: php conduit spotify:setup');

        return 0;
    }

    private function handleSetup(): int
    {
        // Check if already configured
        if ($this->hasStoredCredentials() && ! $this->option('reset')) {
            $this->info('✅ Spotify is already configured');
            $this->line('   Run: php conduit spotify:login (if not authenticated)');
            $this->line('   Run: php conduit spotify:setup --reset (to reconfigure)');

            return 0;
        }

        $this->displayWelcome();

        if (! $this->confirmProceed()) {
            return 0;
        }

        $this->step1CreateApp();
        $credentials = $this->step2GetCredentials();

        if (! $credentials) {
            return 1;
        }

        $this->storeCredentials($credentials);
        $this->displaySuccess();

        return 0;
    }

    private function displayWelcome(): void
    {
        $this->newLine();
        $this->line('🎵 <options=bold>Spotify Integration Setup</options>');
        $this->newLine();
        $this->line('This will guide you through setting up your personal Spotify integration.');
        $this->line('You\'ll need to create a Spotify app (takes 2 minutes).');
        $this->newLine();
    }

    private function confirmProceed(): bool
    {
        return $this->confirm('Ready to set up Spotify integration?', true);
    }

    private function step1CreateApp(): void
    {
        $this->newLine();
        $this->line('📋 <options=bold>Step 1: Create Spotify App</options>');
        $this->newLine();

        $this->line('Opening Spotify Developer Dashboard...');
        $this->openBrowser('https://developer.spotify.com/dashboard/applications');

        $this->newLine();
        $this->line('<options=bold>In your browser:</options>');
        $this->line('1. Click "<options=bold>Create App</options>" button');
        $this->newLine();

        $username = $this->getSystemUsername();
        $appName = "Conduit CLI - {$username}";

        $this->line('<options=bold>Use these app settings:</options>');
        $this->line("   App Name: <comment>{$appName}</comment>");
        $this->line('   App Description: <comment>Personal music control for development workflows</comment>');
        $this->line('   Website: <comment>https://github.com/jordanpartridge/conduit</comment>');
        $this->line('   Redirect URI: <comment>http://127.0.0.1:8888/callback</comment>');
        $this->line('   API: ✅ <comment>Web API</comment>');
        $this->newLine();

        $this->line('💡 <options=bold>Important:</options> Spotify requires 127.0.0.1 (not localhost) for security.');
        $this->line('   Use exactly: <comment>http://127.0.0.1:8888/callback</comment>');

        $this->newLine();
        $this->ask('Press Enter when you\'ve created the app and are viewing its details...');
    }

    private function step2GetCredentials(): ?array
    {
        $this->newLine();
        $this->line('🔑 <options=bold>Step 2: Get Your App Credentials</options>');
        $this->newLine();

        $this->line('In your Spotify app dashboard:');
        $this->line('1. Look for "<options=bold>Client ID</options>" (visible by default)');
        $this->line('2. Click "<options=bold>View client secret</options>" to reveal the secret');
        $this->newLine();

        $clientId = $this->ask('Paste your Client ID');
        if (! $clientId) {
            $this->error('❌ Client ID is required');

            return null;
        }

        $clientSecret = $this->secret('Paste your Client Secret');
        if (! $clientSecret) {
            $this->error('❌ Client Secret is required');

            return null;
        }

        // Basic validation
        if (strlen($clientId) < 20 || strlen($clientSecret) < 20) {
            $this->error('❌ Credentials appear to be invalid (too short)');

            return null;
        }

        return [
            'client_id' => trim($clientId),
            'client_secret' => trim($clientSecret),
        ];
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
        $this->newLine();
        $this->info('✅ Spotify integration configured successfully!');
        $this->newLine();
        $this->line('<options=bold>Next steps:</options>');
        $this->line('1. Run: <comment>php conduit spotify:login</comment> (authenticate with Spotify)');
        $this->line('2. Try: <comment>php conduit spotify:current</comment> (see what\'s playing)');
        $this->line('3. Or: <comment>php conduit spotify:focus coding</comment> (start coding music)');
        $this->newLine();
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
}
