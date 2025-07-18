<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;

class Focus extends Command
{
    protected $signature = 'spotify:focus 
                           {mode? : Focus mode (coding, break, deploy, debug, testing)}
                           {--volume= : Set volume (0-100)}
                           {--shuffle : Enable shuffle}
                           {--generate : Generate focus mood playlists}
                           {--list : List available focus modes}';

    protected $description = 'Start focus music for coding workflows';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        // Use ensureAuthenticated which handles auto-login and token refresh
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Unable to authenticate with Spotify');
            $this->info('💡 Run: php conduit spotify:login');

            return 1;
        }

        if ($this->option('list')) {
            return $this->listFocusModes();
        }

        if ($this->option('generate')) {
            return $this->generateFocusMoodPlaylists($api);
        }

        try {
            $mode = $this->argument('mode') ?? 'coding';
            $volume = $this->option('volume');
            $shuffle = $this->option('shuffle');

            $presets = config('spotify.presets', []);

            if (! isset($presets[$mode])) {
                $this->error("❌ Unknown focus mode: {$mode}");
                $this->line('💡 Available modes: '.implode(', ', array_keys($presets)));
                $this->line('   Or run: php conduit spotify:focus --list');

                return 1;
            }

            $playlistUri = $presets[$mode];

            // Smart device selection - try to use the last active device or activate one
            $this->ensureActiveDevice($api);

            // Set volume if specified or use default
            $targetVolume = $volume ?? config('spotify.auto_play.volume', 70);
            if ($targetVolume) {
                $api->setVolume((int) $targetVolume);
                $this->line("🔊 Volume set to {$targetVolume}%");
            }

            // Enable shuffle if requested
            if ($shuffle) {
                $api->setShuffle(true);
                $this->line('🔀 Shuffle enabled');
            }

            // Start focus playlist
            $success = $api->play($playlistUri);

            if ($success) {
                $emoji = $this->getFocusEmoji($mode);
                $description = $this->getFocusDescription($mode);

                $this->info("{$emoji} {$description}");
                $this->line("🎵 Playing: {$mode} focus playlist");

                // Show current track after a moment
                sleep(1);
                $current = $api->getCurrentTrack();
                if ($current && isset($current['item'])) {
                    $track = $current['item'];
                    $artist = collect($track['artists'])->pluck('name')->join(', ');
                    $this->line("   <info>{$track['name']}</info> by <comment>{$artist}</comment>");
                }

                // Show productivity tip
                $this->newLine();
                $this->line($this->getProductivityTip($mode));

                return 0;
            } else {
                $this->error('❌ Failed to start focus music');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function listFocusModes(): int
    {
        $presets = config('spotify.presets', []);

        $this->info('🎵 Available Focus Modes:');
        $this->newLine();

        foreach ($presets as $mode => $uri) {
            $emoji = $this->getFocusEmoji($mode);
            $description = $this->getFocusDescription($mode);
            $this->line("  {$emoji} <info>{$mode}</info> - {$description}");
        }

        $this->newLine();
        $this->line('💡 Usage: php conduit spotify:focus [mode]');
        $this->line('   Example: php conduit spotify:focus coding --volume=60 --shuffle');

        return 0;
    }

    private function getFocusEmoji(string $mode): string
    {
        return match ($mode) {
            'coding' => '💻',
            'break' => '☕',
            'deploy' => '🚀',
            'debug' => '🐛',
            'testing' => '🧪',
            default => '🎵'
        };
    }

    private function getFocusDescription(string $mode): string
    {
        return match ($mode) {
            'coding' => 'Deep focus coding music activated',
            'break' => 'Relaxing break music started',
            'deploy' => 'Celebration music for successful deployments',
            'debug' => 'Calm debugging music to help concentration',
            'testing' => 'Focused testing music for quality assurance',
            default => 'Focus music activated'
        };
    }

    private function getProductivityTip(string $mode): string
    {
        $tips = [
            'coding' => '💡 Tip: Try the Pomodoro technique - 25 min coding, 5 min break',
            'break' => '💡 Tip: Step away from the screen, stretch, or take a short walk',
            'deploy' => '💡 Tip: Time to celebrate! Your hard work paid off 🎉',
            'debug' => '💡 Tip: Take it slow, read the error messages carefully',
            'testing' => '💡 Tip: Think about edge cases and user scenarios',
        ];

        return $tips[$mode] ?? '💡 Tip: Stay focused and productive!';
    }

    private function generateFocusMoodPlaylists(ApiInterface $api): int
    {
        $this->info('🎯 FOCUS MOOD PLAYLIST GENERATOR');
        $this->line('   Creating curated focus playlists for different work modes...');
        $this->newLine();

        $playlists = $api->getUserPlaylists(50);
        $allTracks = [];
        $focusMoods = [
            'coding' => [
                'keywords' => ['code', 'coding', 'hacker', 'focus', 'work', 'flow', 'programming', 'dev', 'electronic', 'ambient', 'lofi', 'chill'],
                'name' => '💻 Deep Code Focus',
                'description' => 'Ultimate coding focus playlist with electronic, ambient, and lo-fi tracks perfect for deep programming sessions.',
                'track_count' => 50,
                'genres' => ['Electronic/Dance', 'Ambient', 'Chill', 'Lo-Fi', 'Video Game', 'Tech', 'Focus'],
            ],
            'break' => [
                'keywords' => ['chill', 'relax', 'break', 'coffee', 'cafe', 'ambient', 'acoustic', 'soft', 'calm', 'peaceful'],
                'name' => '☕ Break & Recharge',
                'description' => 'Relaxing break music to help you decompress and recharge between coding sessions.',
                'track_count' => 30,
                'genres' => ['Chill', 'Ambient', 'Cafe/Lounge', 'Acoustic', 'Soft', 'Calm'],
            ],
            'deploy' => [
                'keywords' => ['celebration', 'energy', 'victory', 'success', 'pump', 'epic', 'achievement', 'win', 'rock', 'electronic'],
                'name' => '🚀 Deploy Victory',
                'description' => 'High-energy celebration music for successful deployments and project completions.',
                'track_count' => 25,
                'genres' => ['Rock', 'Electronic/Dance', 'Pop', 'Energy', 'Victory'],
            ],
            'debug' => [
                'keywords' => ['calm', 'focus', 'patience', 'zen', 'meditation', 'instrumental', 'classical', 'ambient', 'slow'],
                'name' => '🐛 Debug Zen',
                'description' => 'Calm, focused music to help maintain patience and clarity while debugging complex issues.',
                'track_count' => 40,
                'genres' => ['Ambient', 'Classical', 'Instrumental', 'Calm', 'Focus'],
            ],
            'testing' => [
                'keywords' => ['systematic', 'methodical', 'focus', 'precision', 'quality', 'instrumental', 'electronic', 'minimal'],
                'name' => '🧪 Testing Flow',
                'description' => 'Systematic, methodical music for quality assurance and testing workflows.',
                'track_count' => 35,
                'genres' => ['Electronic/Dance', 'Instrumental', 'Minimal', 'Focus', 'Systematic'],
            ],
        ];

        $this->task('Analyzing music library for focus mood curation', function () use ($api, $playlists, &$allTracks) {
            foreach ($playlists as $playlist) {
                $tracks = $api->getPlaylistTracks($playlist['id']);
                $playlistLower = strtolower($playlist['name']);

                foreach ($tracks as $track) {
                    $trackId = $track['track']['id'] ?? null;
                    $trackUri = $track['track']['uri'] ?? null;
                    $trackName = $track['track']['name'] ?? 'Unknown';
                    $artistName = $track['track']['artists'][0]['name'] ?? 'Unknown';

                    if (! $trackId || ! $trackUri) {
                        continue;
                    }

                    $allTracks[] = [
                        'id' => $trackId,
                        'name' => $trackName,
                        'artist' => $artistName,
                        'uri' => $trackUri,
                        'playlist' => $playlist['name'],
                        'playlist_lower' => $playlistLower,
                        'genre' => $this->inferGenreFromPlaylist($playlist['name'], $artistName),
                    ];
                }
            }

            return true;
        });

        $this->info('🎵 FOCUS MOOD ANALYSIS:');
        $this->line('   📊 Total Tracks Analyzed: '.count($allTracks));
        $this->newLine();

        $createdPlaylists = 0;
        foreach ($focusMoods as $mode => $config) {
            $this->info("🎯 Generating {$config['name']} playlist...");

            $selectedTracks = $this->selectTracksForFocusMode($allTracks, $config);

            if (count($selectedTracks) >= 10) {
                $this->line('   ✅ Found '.count($selectedTracks).' matching tracks');

                if ($this->confirm("   Create \"{$config['name']}\" playlist?")) {
                    $this->createFocusMoodPlaylist($api, $config, $selectedTracks);
                    $createdPlaylists++;
                }
            } else {
                $this->line('   ❌ Only found '.count($selectedTracks).' tracks, need at least 10');
            }

            $this->newLine();
        }

        $this->info('🎯 FOCUS MOOD GENERATION COMPLETE!');
        $this->line("   ✅ Created {$createdPlaylists} focus mood playlists");
        $this->line('   💡 Perfect for different work modes and energy levels');

        return 0;
    }

    private function selectTracksForFocusMode(array $allTracks, array $config): array
    {
        $selectedTracks = [];
        $usedTrackIds = [];
        $artistCount = [];

        foreach ($allTracks as $track) {
            // Skip if already used
            if (in_array($track['id'], $usedTrackIds)) {
                continue;
            }

            // Check if track matches the focus mode
            $score = 0;

            // Keyword matching in playlist name
            foreach ($config['keywords'] as $keyword) {
                if (str_contains($track['playlist_lower'], $keyword)) {
                    $score += 3;
                }
            }

            // Genre matching
            if (isset($config['genres']) && in_array($track['genre'], $config['genres'])) {
                $score += 5;
            }

            // Artist diversity (max 3 tracks per artist)
            if (($artistCount[$track['artist']] ?? 0) >= 3) {
                continue;
            }

            // Only include tracks with some relevance
            if ($score >= 3) {
                $track['focus_score'] = $score;
                $selectedTracks[] = $track;
                $usedTrackIds[] = $track['id'];
                $artistCount[$track['artist']] = ($artistCount[$track['artist']] ?? 0) + 1;
            }

            // Stop when we have enough tracks
            if (count($selectedTracks) >= $config['track_count']) {
                break;
            }
        }

        // Sort by focus score (highest first)
        usort($selectedTracks, fn ($a, $b) => $b['focus_score'] <=> $a['focus_score']);

        return $selectedTracks;
    }

    private function createFocusMoodPlaylist(ApiInterface $api, array $config, array $selectedTracks): void
    {
        $playlist = null;

        $this->task("Creating \"{$config['name']}\" playlist", function () use ($api, $config, $selectedTracks, &$playlist) {
            // Extract URIs and shuffle for variety
            $trackUris = array_column($selectedTracks, 'uri');
            shuffle($trackUris);

            if (empty($trackUris)) {
                throw new \Exception('No tracks found to add to playlist');
            }

            // Create the playlist
            $playlist = $api->createPlaylist(
                $config['name'],
                $config['description'].' Generated by Conduit Focus.',
                false
            );

            // Add tracks to playlist
            $chunks = array_chunk($trackUris, 100);
            foreach ($chunks as $chunk) {
                $api->addTracksToPlaylist($playlist['id'], $chunk);
            }

            return true;
        });

        $this->line("   ✅ \"{$config['name']}\" created with ".count($selectedTracks).' tracks');
        $this->line('   🔗 https://open.spotify.com/playlist/'.$playlist['id']);
    }

    private function inferGenreFromPlaylist(string $playlistName, string $artistName): string
    {
        $playlistLower = strtolower($playlistName);

        // Map playlist names to genres
        $genreMap = [
            'dance' => 'Electronic/Dance',
            'electronic' => 'Electronic/Dance',
            'edm' => 'Electronic/Dance',
            'hip hop' => 'Hip-Hop/Rap',
            'rap' => 'Hip-Hop/Rap',
            'rock' => 'Rock',
            'metal' => 'Metal',
            'punk' => 'Punk',
            'pop' => 'Pop',
            'jazz' => 'Jazz',
            'blues' => 'Blues',
            'folk' => 'Folk',
            'country' => 'Country',
            'classical' => 'Classical',
            'indie' => 'Indie',
            'alternative' => 'Alternative',
            'ambient' => 'Ambient',
            'chill' => 'Chill',
            'lofi' => 'Lo-Fi',
            'funk' => 'Funk',
            'soul' => 'Soul',
            'r&b' => 'R&B',
            'reggae' => 'Reggae',
            'latin' => 'Latin',
            'world' => 'World',
            'soundtrack' => 'Soundtrack',
            'christmas' => 'Holiday',
            'holiday' => 'Holiday',
            'workout' => 'Workout',
            'focus' => 'Focus',
            'study' => 'Study',
            'coding' => 'Coding',
            'hacker' => 'Tech',
            'work' => 'Work',
            '8 bit' => 'Video Game',
            'game' => 'Video Game',
            'bit' => 'Video Game',
            'gta' => 'Video Game',
            'emo' => 'Emo',
            'hardcore' => 'Hardcore',
            'cafe' => 'Cafe/Lounge',
            'lounge' => 'Cafe/Lounge',
            '90s' => 'Retro',
            '80s' => 'Retro',
            '70s' => 'Retro',
            'vintage' => 'Retro',
            'vibe' => 'Vibes',
            'mood' => 'Mood',
            'winter' => 'Seasonal',
            'summer' => 'Seasonal',
        ];

        foreach ($genreMap as $keyword => $genre) {
            if (str_contains($playlistLower, $keyword)) {
                return $genre;
            }
        }

        return 'Mixed';
    }

    /**
     * Ensure there's an active Spotify device available for playback.
     */
    private function ensureActiveDevice(ApiInterface $api): void
    {
        try {
            // First check if we have a currently active device
            $currentPlayback = $api->getCurrentPlayback();
            if ($currentPlayback && isset($currentPlayback['device']) && $currentPlayback['device']['is_active']) {
                $device = $currentPlayback['device'];
                $this->line("🎵 Using active device: {$device['name']}");

                return;
            }

            // No active device, try to find and activate one
            $devices = $api->getAvailableDevices();

            if (empty($devices)) {
                $this->warn('⚠️  No Spotify devices found');
                $this->line('💡 Make sure Spotify is open on a device:');
                $this->line('  • Open Spotify on your phone, computer, or web player');
                $this->line('  • Then try this command again');

                return;
            }

            // Check if any device is already active
            $activeDevice = collect($devices)->firstWhere('is_active', true);
            if ($activeDevice) {
                $this->line("🎵 Using active device: {$activeDevice['name']}");

                return;
            }

            // No active device, try to activate the first available one
            $firstDevice = $devices[0];
            $this->line("🔄 Activating device: {$firstDevice['name']}");

            if ($api->transferPlayback($firstDevice['id'], false)) {
                sleep(1); // Give device time to activate
                $this->line('✅ Device activated successfully');
            } else {
                $this->warn("⚠️  Could not activate device: {$firstDevice['name']}");
            }

        } catch (\Exception $e) {
            // If device detection fails, continue anyway - the play command will handle it
            $this->warn("⚠️  Device detection failed: {$e->getMessage()}");
        }
    }
}
