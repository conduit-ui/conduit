<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

class Configure extends Command
{
    protected $signature = 'spotify:configure 
                           {--focus-playlists : Configure focus mode playlists}
                           {--reset : Reset existing credentials}';

    protected $description = 'Configure Spotify integration settings and personal preferences';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        // Handle legacy reset option
        if ($this->option('reset')) {
            $this->warn('⚠️  Use spotify:setup --reset for credential reset.');
            return $this->call('spotify:setup', ['--reset' => true]);
        }

        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Unable to authenticate with Spotify');
            $this->info('💡 Run: php conduit spotify:login');
            return 1;
        }

        if ($this->option('focus-playlists')) {
            return $this->configureFocusPlaylists($api);
        }

        // Interactive menu for configuration options
        $choice = select(
            label: 'What would you like to configure?',
            options: [
                'focus-playlists' => '🎵 Focus Mode Playlists',
                'setup' => '🔧 Setup/Credentials',
            ]
        );

        return match ($choice) {
            'focus-playlists' => $this->configureFocusPlaylists($api),
            'setup' => $this->call('spotify:setup'),
        };
    }

    private function configureFocusPlaylists(ApiInterface $api): int
    {
        $this->info('🎵 Configure Focus Mode Playlists');
        $this->newLine();

        // Get user's playlists
        $this->line('📋 Fetching your playlists...');
        $playlists = $api->getUserPlaylists(50);

        if (empty($playlists)) {
            $this->error('❌ No playlists found');
            return 1;
        }

        // Current focus modes
        $focusModes = [
            'coding' => '💻 Coding - Deep focus programming music',
            'break' => '☕ Break - Relaxing music for breaks',
            'deploy' => '🚀 Deploy - Celebration music for deployments',
            'debug' => '🐛 Debug - Calm music for debugging',
            'testing' => '🧪 Testing - Focused music for quality assurance',
        ];

        // Get current configuration
        $currentConfig = $this->getCurrentFocusConfig();

        foreach ($focusModes as $mode => $description) {
            $this->newLine();
            $this->line("<fg=cyan;options=bold>{$description}</fg=cyan;options=bold>");
            
            // Show current assignment
            if (isset($currentConfig[$mode])) {
                $currentPlaylist = $this->findPlaylistByUri($playlists, $currentConfig[$mode]);
                if ($currentPlaylist) {
                    $this->line("   Current: {$currentPlaylist['name']}");
                } else {
                    $this->line("   Current: Generic Spotify playlist");
                }
            }

            // Let user choose new playlist
            $playlistChoices = ['skip' => '⏭️ Skip (keep current)'];
            foreach ($playlists as $playlist) {
                $trackCount = $playlist['tracks']['total'];
                $playlistChoices[$playlist['uri']] = "{$playlist['name']} ({$trackCount} tracks)";
            }

            $choice = select(
                label: "Choose playlist for {$mode} mode:",
                options: $playlistChoices
            );

            if ($choice !== 'skip') {
                $this->saveFocusPlaylist($mode, $choice);
                $playlistName = $this->findPlaylistByUri($playlists, $choice)['name'];
                $this->info("✅ {$mode} mode set to: {$playlistName}");
            }
        }

        $this->newLine();
        $this->info('🎉 Focus playlists configured!');
        $this->line('💡 Test with: php conduit spotify:focus coding');

        return 0;
    }

    private function getCurrentFocusConfig(): array
    {
        // First check user's custom assignments
        $userConfig = Cache::store('file')->get('spotify_focus_playlists', []);
        
        // Fall back to default config
        $defaultConfig = config('spotify.presets', []);
        
        return array_merge($defaultConfig, $userConfig);
    }

    private function saveFocusPlaylist(string $mode, string $playlistUri): void
    {
        $currentConfig = Cache::store('file')->get('spotify_focus_playlists', []);
        $currentConfig[$mode] = $playlistUri;
        
        Cache::store('file')->put('spotify_focus_playlists', $currentConfig, now()->addYear());
    }

    private function findPlaylistByUri(array $playlists, string $uri): ?array
    {
        foreach ($playlists as $playlist) {
            if ($playlist['uri'] === $uri) {
                return $playlist;
            }
        }
        return null;
    }
}
