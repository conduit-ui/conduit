<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Concerns\ShowsSpotifyStatus;
use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;

class Play extends Command
{
    use ShowsSpotifyStatus;
    protected $signature = 'spotify:play 
                           {uri? : Spotify URI, preset name, or search query}
                           {--device= : Device ID to play on}
                           {--shuffle : Enable shuffle mode}
                           {--volume= : Set volume (0-100)}';

    protected $description = 'Start playing music on Spotify';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (! $auth->ensureAuthenticated()) {
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
                    if (! $auth->ensureAuthenticated()) {
                        $this->error('❌ Authentication failed. Please try again.');

                        return 1;
                    }
                } else {
                    $this->error('❌ Login failed. Please try again.');

                    return 1;
                }
            } else {
                $this->info('💡 Run: conduit spotify:login');

                return 1;
            }
        }

        try {
            $uri = $this->argument('uri');
            $deviceId = $this->option('device');
            $shuffle = $this->option('shuffle');
            $volume = $this->option('volume');

            // Check for available devices if no specific device provided
            if (! $deviceId) {
                // First, check current playback for last active device
                $currentPlayback = $api->getCurrentPlayback();
                if ($currentPlayback && isset($currentPlayback['device'])) {
                    $lastDevice = $currentPlayback['device'];
                    $this->info("🎵 Using last active device: {$lastDevice['name']}");
                    $deviceId = $lastDevice['id'];
                } else {
                    // Fallback to available devices
                    $devices = $api->getAvailableDevices();
                    $activeDevice = collect($devices)->firstWhere('is_active', true);

                    if ($activeDevice) {
                        $deviceId = $activeDevice['id'];
                        $this->info("🎵 Using active device: {$activeDevice['name']}");
                    } elseif (! empty($devices)) {
                        // Try to activate the first available device
                        $firstDevice = $devices[0];
                        $this->info("🔄 Activating device: {$firstDevice['name']}");
                        if ($api->transferPlayback($firstDevice['id'], false)) {
                            $deviceId = $firstDevice['id'];
                            sleep(1); // Give device time to activate
                        }
                    }
                }
            }

            // Handle preset shortcuts and search queries
            if ($uri && ! str_starts_with($uri, 'spotify:')) {
                $presets = config('spotify.presets', []);
                if (isset($presets[$uri])) {
                    $uri = $presets[$uri];
                    $this->info("🎵 Playing preset: {$uri}");
                } else {
                    // Treat as search query
                    $this->info("🔍 Searching for: \"{$uri}\"");
                    $searchResults = $api->search($uri, ['track', 'artist'], 5);

                    // Check if we have a popular track match first
                    if (! empty($searchResults['tracks']['items'])) {
                        $tracks = $searchResults['tracks']['items'];

                        // Look for exact track name match
                        $exactTrackMatch = collect($tracks)->first(function ($track) use ($uri) {
                            return strtolower($track['name']) === strtolower($uri);
                        });

                        if ($exactTrackMatch) {
                            $uri = $exactTrackMatch['uri'];
                            $artist = collect($exactTrackMatch['artists'])->pluck('name')->join(', ');
                            $this->info("🎵 Found track: {$exactTrackMatch['name']} by {$artist}");
                        } else {
                            // No exact track match, try artist
                            if (! empty($searchResults['artists']['items'])) {
                                $artists = $searchResults['artists']['items'];

                                // Look for exact artist match
                                $exactArtistMatch = collect($artists)->first(function ($artist) use ($uri) {
                                    return strtolower($artist['name']) === strtolower($uri);
                                });

                                if ($exactArtistMatch) {
                                    $uri = $exactArtistMatch['uri'];
                                    $this->info("🎵 Found artist: {$exactArtistMatch['name']}");
                                } else {
                                    // Fall back to first track
                                    $track = $tracks[0];
                                    $uri = $track['uri'];
                                    $artist = collect($track['artists'])->pluck('name')->join(', ');
                                    $this->info("🎵 Found track: {$track['name']} by {$artist}");
                                }
                            } else {
                                // Fall back to first track
                                $track = $tracks[0];
                                $uri = $track['uri'];
                                $artist = collect($track['artists'])->pluck('name')->join(', ');
                                $this->info("🎵 Found track: {$track['name']} by {$artist}");
                            }
                        }
                    } elseif (! empty($searchResults['artists']['items'])) {
                        $artist = $searchResults['artists']['items'][0];
                        $uri = $artist['uri'];
                        $this->info("🎵 Found artist: {$artist['name']}");
                    } else {
                        $this->error("❌ No results found for: \"{$this->argument('uri')}\"");

                        return 1;
                    }
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

                // Show status bar after playback starts
                sleep(1);
                $this->showSpotifyStatusBar();

                return 0;
            } else {
                $this->error('❌ Failed to start playback');

                return 1;
            }

        } catch (\Exception $e) {
            $message = $e->getMessage();

            // Handle different error scenarios gracefully
            if (str_contains($message, 'No active')) {
                $this->error('❌ No active Spotify device found');
                $this->newLine();
                $this->info('💡 Make sure Spotify is open on a device:');
                $this->line('  • Open Spotify on your phone, computer, or web player');
                $this->line('  • Start playing any song to activate the device');
                $this->line('  • Then try this command again');

                return 1;
            }

            if (str_contains($message, 'Already playing')) {
                $this->warn('⚠️  Already playing or action not permitted');
                $this->newLine();

                // Show current track info instead
                try {
                    $current = $api->getCurrentTrack();
                    if ($current && isset($current['item'])) {
                        $track = $current['item'];
                        $artist = collect($track['artists'])->pluck('name')->join(', ');
                        $this->info('🎵 Currently playing:');
                        $this->line("   <info>{$track['name']}</info> by <comment>{$artist}</comment>");

                        // Suggest alternatives
                        $this->newLine();
                        $this->info('💡 Try these commands instead:');
                        $this->line('  • <comment>conduit spotify:pause</comment> - Pause current track');
                        $this->line('  • <comment>conduit spotify:next</comment> - Skip to next track');
                        $this->line('  • <comment>conduit spotify:play [different-uri]</comment> - Play something else');

                        return 0; // Don't treat as error, just informational
                    }
                } catch (\Exception $currentError) {
                    // If we can't get current track, just show the warning
                }

                return 0;
            }

            if (str_contains($message, 'Premium')) {
                $this->error('❌ Premium Spotify subscription required');
                $this->newLine();
                $this->info('💡 This action requires Spotify Premium');

                return 1;
            }

            // General error handling
            $this->error("❌ Error: {$message}");

            return 1;
        }
    }
}
