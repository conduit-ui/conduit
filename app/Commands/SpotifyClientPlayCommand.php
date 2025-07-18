<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Jordanpartridge\SpotifyClient\SpotifyClient;

class SpotifyClientPlayCommand extends Command
{
    protected $signature = 'spotify:play2 
                           {uri? : Spotify URI (track, album, playlist, or artist)}
                           {--device= : Device ID to play on}
                           {--volume= : Set volume (0-100)}';

    protected $description = 'Start playing music on Spotify (using spotify-client package)';

    public function handle(SpotifyClient $client): int
    {
        try {
            $uri = $this->argument('uri');
            $deviceId = $this->option('device');
            $volume = $this->option('volume');

            // Set volume if specified
            if ($volume !== null) {
                $volume = max(0, min(100, (int) $volume));
                $response = $client->player()->volume($volume, $deviceId);

                if ($response->successful()) {
                    $this->info("🔊 Volume set to {$volume}%");
                } else {
                    $this->warn("⚠️ Failed to set volume: {$response->status()}");
                }
            }

            // Start playback
            if ($uri) {
                // Determine if it's a context URI (playlist/album) or track URIs
                if (str_contains($uri, 'playlist:') || str_contains($uri, 'album:') || str_contains($uri, 'artist:')) {
                    $response = $client->player()->playContext($uri, deviceId: $deviceId);
                } else {
                    // Assume it's a track URI
                    $response = $client->player()->playTracks([$uri], $deviceId);
                }
            } else {
                // Resume playback
                $response = $client->player()->resume($deviceId);
            }

            if ($response->successful()) {
                if ($uri) {
                    $this->info("▶️  Playing: {$uri}");
                } else {
                    $this->info('▶️  Resuming playback');
                }

                // Show current track after a moment
                sleep(1);
                $currentResponse = $client->player()->currentlyPlaying();

                if ($currentResponse->successful()) {
                    $current = $currentResponse->json();
                    if ($current && isset($current['item'])) {
                        $track = $current['item'];
                        $artist = collect($track['artists'])->pluck('name')->join(', ');
                        $this->line("🎵 <info>{$track['name']}</info> by <comment>{$artist}</comment>");
                    }
                }

                return 0;
            } else {
                $this->error("❌ Failed to start playback: {$response->status()} {$response->body()}");

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            if (str_contains($e->getMessage(), 'No active') || str_contains($e->getMessage(), '404')) {
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
