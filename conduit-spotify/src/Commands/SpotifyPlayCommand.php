<?php

namespace JordanPartridge\ConduitSpotify\Commands;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;

class SpotifyPlayCommand extends Command
{
    protected $signature = 'spotify:play 
                           {uri? : Spotify URI (track, album, playlist, or artist)}
                           {--device= : Device ID to play on}
                           {--shuffle : Enable shuffle mode}
                           {--volume= : Set volume (0-100)}';

    protected $description = 'Start playing music on Spotify';

    public function handle(SpotifyAuthInterface $auth, SpotifyApiInterface $api): int
    {
        if (! $auth->isAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:auth');

            return 1;
        }

        try {
            $uri = $this->argument('uri');
            $deviceId = $this->option('device');
            $shuffle = $this->option('shuffle');
            $volume = $this->option('volume');

            // Handle preset shortcuts
            if ($uri && ! str_starts_with($uri, 'spotify:')) {
                $presets = config('spotify.presets', []);
                if (isset($presets[$uri])) {
                    $uri = $presets[$uri];
                    $this->info("🎵 Playing preset: {$uri}");
                }
            }

            // Set volume if specified
            if ($volume !== null) {
                $volume = max(0, min(100, (int) $volume));
                $api->setVolume($volume, $deviceId);
                $this->info("🔊 Volume set to {$volume}%");
            }

            // Enable shuffle if requested
            if ($shuffle) {
                $api->setShuffle(true, $deviceId);
                $this->info('🔀 Shuffle enabled');
            }

            // Start playback
            $success = $api->play($uri, $deviceId);

            if ($success) {
                if ($uri) {
                    $this->info("▶️  Playing: {$uri}");
                } else {
                    $this->info('▶️  Resuming playback');
                }

                // Show current track after a moment
                sleep(1);
                $current = $api->getCurrentTrack();
                if ($current && isset($current['item'])) {
                    $track = $current['item'];
                    $artist = collect($track['artists'])->pluck('name')->join(', ');
                    $this->line("🎵 <info>{$track['name']}</info> by <comment>{$artist}</comment>");
                }

                return 0;
            } else {
                $this->error('❌ Failed to start playback');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            if (str_contains($e->getMessage(), 'No active')) {
                $this->newLine();
                $this->info('💡 Make sure Spotify is open on a device:');
                $this->line('  • Open Spotify on your phone, computer, or web player');
                $this->line('  • Start playing any song to activate the device');
                $this->line('  • Then try this command again');
            }

            return 1;
        }
    }
}
