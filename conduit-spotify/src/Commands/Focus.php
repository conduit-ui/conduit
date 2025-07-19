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
                           {--list : List available focus modes}
                           {--skip= : Record that you skipped a focus mode}';

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

        if ($skipMode = $this->option('skip')) {
            return $this->recordSkip($skipMode);
        }

        try {
            $mode = $this->argument('mode') ?? 'coding';
            $volume = $this->option('volume');
            $shuffle = $this->option('shuffle');

            $presets = $this->getFocusPresets();

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

                // Track successful focus session start
                $this->trackFocusUsage($mode, 'start');

                // Show productivity tip and learning status
                $this->newLine();
                $this->line($this->getProductivityTip($mode));
                $this->showLearningStats($mode);

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
        $presets = $this->getFocusPresets();

        $this->info('🎵 Available Focus Modes:');
        $this->newLine();

        // Show recommendation first if available
        $recommended = $this->getRecommendedFocusMode();
        if ($recommended && isset($presets[$recommended])) {
            $emoji = $this->getFocusEmoji($recommended);
            $description = $this->getFocusDescription($recommended);
            $this->line("  🌟 <info>{$recommended}</info> - {$description} <fg=green>(Recommended)</fg=green>");
            $this->newLine();
        }

        foreach ($presets as $mode => $uri) {
            // Skip recommended mode since we already showed it
            if ($mode === $recommended) {
                continue;
            }
            
            $emoji = $this->getFocusEmoji($mode);
            $description = $this->getFocusDescription($mode);
            
            // Add usage stats if available
            $stats = \Illuminate\Support\Facades\Cache::store('file')->get('spotify_focus_stats', []);
            $statsText = '';
            if (isset($stats[$mode])) {
                $modeStats = $stats[$mode];
                if ($modeStats['total_starts'] > 0) {
                    $streak = $modeStats['streak'] > 0 ? " 🔥{$modeStats['streak']}d" : '';
                    $statsText = " <fg=yellow>({$modeStats['total_starts']} uses{$streak})</fg=yellow>";
                }
            }
            
            $this->line("  {$emoji} <info>{$mode}</info> - {$description}{$statsText}");
        }

        $this->newLine();
        $this->line('💡 Usage: php conduit spotify:focus [mode]');
        $this->line('   Example: php conduit spotify:focus coding --volume=60 --shuffle');
        $this->line('🔧 Configure: php conduit spotify:configure --focus-playlists');

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
                $this->line("🎵 Using active device: {$device['name']} ({$device['type']})");

                return;
            }

            // No active device, try to find and activate one
            $devices = $api->getAvailableDevices();

            if (empty($devices)) {
                $this->warn('⚠️  No Spotify devices found');
                $this->line('💡 Make sure Spotify is open on a device:');
                $this->line('  • Open Spotify on your phone, computer, or web player');
                $this->line('  • Wait a moment for devices to register');
                $this->line('  • Then try this command again');

                return;
            }

            // Check if any device is already active
            $activeDevice = collect($devices)->firstWhere('is_active', true);
            if ($activeDevice) {
                $this->line("🎵 Using active device: {$activeDevice['name']} ({$activeDevice['type']})");

                return;
            }

            // Smart device selection priority
            $preferredDevice = $this->selectBestDevice($devices);
            
            if ($preferredDevice) {
                $this->line("🔄 Activating device: {$preferredDevice['name']} ({$preferredDevice['type']})");

                $success = $this->attemptDeviceActivation($api, $preferredDevice);
                
                if ($success) {
                    $this->line('✅ Device activated successfully');
                } else {
                    // Try fallback devices
                    $this->tryFallbackDevices($api, $devices, $preferredDevice['id']);
                }
            }

        } catch (\Exception $e) {
            // If device detection fails, continue anyway - the play command will handle it
            $this->warn("⚠️  Device detection failed: {$e->getMessage()}");
            $this->line('💡 Music will attempt to play on the last used device');
        }
    }

    /**
     * Select the best available device based on priority
     */
    private function selectBestDevice(array $devices): ?array
    {
        // Device priority: Computer > Smartphone > Speaker > Other
        $priorities = [
            'Computer' => 4,
            'Smartphone' => 3,
            'Speaker' => 2,
            'TV' => 1,
            'Unknown' => 0
        ];

        $scoredDevices = [];
        foreach ($devices as $device) {
            $type = $device['type'] ?? 'Unknown';
            $score = $priorities[$type] ?? 0;
            
            // Boost score for devices that support volume control
            if ($device['supports_volume'] ?? false) {
                $score += 2;
            }
            
            // Boost score for recently active devices
            if ($device['is_active'] ?? false) {
                $score += 5;
            }
            
            $scoredDevices[] = [
                'device' => $device,
                'score' => $score
            ];
        }

        // Sort by score (highest first)
        usort($scoredDevices, fn($a, $b) => $b['score'] <=> $a['score']);

        return !empty($scoredDevices) ? $scoredDevices[0]['device'] : null;
    }

    /**
     * Attempt to activate a device with retry logic
     */
    private function attemptDeviceActivation(ApiInterface $api, array $device): bool
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            
            try {
                if ($api->transferPlayback($device['id'], false)) {
                    // Wait and verify activation
                    sleep(2);
                    
                    $currentPlayback = $api->getCurrentPlayback();
                    if ($currentPlayback && 
                        isset($currentPlayback['device']) && 
                        $currentPlayback['device']['id'] === $device['id']) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                $this->warn("⚠️  Activation attempt {$attempt} failed: {$e->getMessage()}");
            }

            if ($attempt < $maxAttempts) {
                $this->line("⏳ Retrying in 2 seconds... (attempt {$attempt}/{$maxAttempts})");
                sleep(2);
            }
        }

        return false;
    }

    /**
     * Try fallback devices if primary selection fails
     */
    private function tryFallbackDevices(ApiInterface $api, array $devices, string $excludeId): void
    {
        $fallbackDevices = array_filter($devices, fn($d) => $d['id'] !== $excludeId);
        
        foreach ($fallbackDevices as $device) {
            $this->line("🔄 Trying fallback device: {$device['name']} ({$device['type']})");
            
            if ($this->attemptDeviceActivation($api, $device)) {
                $this->line('✅ Fallback device activated successfully');
                return;
            }
        }

        $this->warn('⚠️  Could not activate any devices');
        $this->line('💡 Available devices:');
        foreach ($devices as $device) {
            $status = ($device['is_active'] ?? false) ? '(active)' : '(inactive)';
            $this->line("  • {$device['name']} ({$device['type']}) {$status}");
        }
    }

    /**
     * Get focus presets with user customizations taking precedence
     */
    private function getFocusPresets(): array
    {
        // Get user's custom assignments first
        $userConfig = \Illuminate\Support\Facades\Cache::store('file')->get('spotify_focus_playlists', []);
        
        // Fall back to default config
        $defaultConfig = config('spotify.presets', []);
        
        // Merge user preferences over defaults
        return array_merge($defaultConfig, $userConfig);
    }

    /**
     * Track focus mode usage for learning and recommendations
     */
    private function trackFocusUsage(string $mode, string $action): void
    {
        try {
            $stats = \Illuminate\Support\Facades\Cache::store('file')->get('spotify_focus_stats', []);
            
            $today = now()->format('Y-m-d');
            $currentHour = now()->format('H');
            
            // Initialize structure if needed
            if (!isset($stats[$mode])) {
                $stats[$mode] = [
                    'total_starts' => 0,
                    'total_skips' => 0,
                    'daily_usage' => [],
                    'hourly_patterns' => [],
                    'last_used' => null,
                    'streak' => 0,
                    'favorite_times' => [],
                ];
            }
            
            $modeStats = &$stats[$mode];
            
            switch ($action) {
                case 'start':
                    $modeStats['total_starts']++;
                    $modeStats['last_used'] = now()->toISOString();
                    
                    // Track daily usage
                    if (!isset($modeStats['daily_usage'][$today])) {
                        $modeStats['daily_usage'][$today] = 0;
                    }
                    $modeStats['daily_usage'][$today]++;
                    
                    // Track hourly patterns
                    if (!isset($modeStats['hourly_patterns'][$currentHour])) {
                        $modeStats['hourly_patterns'][$currentHour] = 0;
                    }
                    $modeStats['hourly_patterns'][$currentHour]++;
                    
                    // Calculate usage streak
                    $modeStats['streak'] = $this->calculateUsageStreak($modeStats['daily_usage']);
                    break;
                    
                case 'skip':
                    $modeStats['total_skips']++;
                    break;
            }
            
            // Save updated stats
            \Illuminate\Support\Facades\Cache::store('file')->put('spotify_focus_stats', $stats, now()->addYear());
            
        } catch (\Exception $e) {
            // Don't let tracking failures affect the main functionality
            \Log::debug("Focus tracking failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Calculate the current usage streak for a mode
     */
    private function calculateUsageStreak(array $dailyUsage): int
    {
        $streak = 0;
        $currentDate = now();
        
        // Go backwards from today counting consecutive days with usage
        for ($i = 0; $i < 30; $i++) { // Check last 30 days max
            $checkDate = $currentDate->copy()->subDays($i)->format('Y-m-d');
            
            if (isset($dailyUsage[$checkDate]) && $dailyUsage[$checkDate] > 0) {
                $streak++;
            } else {
                break; // Streak broken
            }
        }
        
        return $streak;
    }
    
    /**
     * Show learning insights and usage stats
     */
    private function showLearningStats(string $mode): void
    {
        $stats = \Illuminate\Support\Facades\Cache::store('file')->get('spotify_focus_stats', []);
        
        if (!isset($stats[$mode])) {
            return;
        }
        
        $modeStats = $stats[$mode];
        $insights = $this->generateLearningInsights($mode, $modeStats);
        
        if (!empty($insights)) {
            $this->newLine();
            $this->line('🧠 <fg=blue;options=bold>AI Insights</fg=blue;options=bold>');
            foreach ($insights as $insight) {
                $this->line("   {$insight}");
            }
        }
    }
    
    /**
     * Generate smart insights based on usage patterns
     */
    private function generateLearningInsights(string $mode, array $stats): array
    {
        $insights = [];
        
        // Usage frequency insights
        if ($stats['total_starts'] >= 5) {
            $successRate = $stats['total_starts'] / ($stats['total_starts'] + $stats['total_skips']) * 100;
            
            if ($successRate >= 85) {
                $insights[] = "💚 You love {$mode} mode! " . round($successRate) . "% success rate";
            } elseif ($successRate < 60) {
                $insights[] = "🤔 Consider tweaking {$mode} playlist - only " . round($successRate) . "% satisfaction";
            }
        }
        
        // Streak insights
        if ($stats['streak'] >= 3) {
            $insights[] = "🔥 {$stats['streak']} day {$mode} streak! Keep it up!";
        }
        
        // Time pattern insights
        if (!empty($stats['hourly_patterns'])) {
            $favoriteHour = array_keys($stats['hourly_patterns'], max($stats['hourly_patterns']))[0];
            $favoriteTime = sprintf('%02d:00', $favoriteHour);
            
            if ($stats['hourly_patterns'][$favoriteHour] >= 3) {
                $currentHour = now()->format('H');
                if (abs($currentHour - $favoriteHour) <= 1) {
                    $insights[] = "⏰ Perfect timing! You usually {$mode} around {$favoriteTime}";
                } else {
                    $insights[] = "💡 You're most productive with {$mode} around {$favoriteTime}";
                }
            }
        }
        
        // Usage milestone insights
        if ($stats['total_starts'] == 10) {
            $insights[] = "🎉 10th {$mode} session! You're building great habits";
        } elseif ($stats['total_starts'] == 50) {
            $insights[] = "🏆 50 {$mode} sessions! You're a focus master";
        } elseif ($stats['total_starts'] == 100) {
            $insights[] = "🚀 100 {$mode} sessions! Incredible dedication";
        }
        
        return array_slice($insights, 0, 2); // Limit to 2 insights to avoid clutter
    }
    
    /**
     * Get recommended focus mode based on learning patterns
     */
    private function getRecommendedFocusMode(): ?string
    {
        $stats = \Illuminate\Support\Facades\Cache::store('file')->get('spotify_focus_stats', []);
        $currentHour = (int) now()->format('H');
        $recommendations = [];
        
        foreach ($stats as $mode => $modeStats) {
            $score = 0;
            
            // Recent usage boost
            if (isset($modeStats['last_used'])) {
                $lastUsed = \Carbon\Carbon::parse($modeStats['last_used']);
                $daysSince = now()->diffInDays($lastUsed);
                if ($daysSince <= 7) {
                    $score += 10 - $daysSince; // More recent = higher score
                }
            }
            
            // Time pattern matching
            if (isset($modeStats['hourly_patterns'][$currentHour])) {
                $score += $modeStats['hourly_patterns'][$currentHour] * 5;
            }
            
            // Success rate boost
            $total = $modeStats['total_starts'] + $modeStats['total_skips'];
            if ($total > 0) {
                $successRate = $modeStats['total_starts'] / $total;
                $score += $successRate * 10;
            }
            
            if ($score > 0) {
                $recommendations[$mode] = $score;
            }
        }
        
        if (empty($recommendations)) {
            return null;
        }
        
        arsort($recommendations);
        return array_key_first($recommendations);
    }
    
    /**
     * Record that user skipped a focus mode for learning
     */
    private function recordSkip(string $mode): int
    {
        $presets = $this->getFocusPresets();
        
        if (!isset($presets[$mode])) {
            $this->error("❌ Unknown focus mode: {$mode}");
            $this->line('💡 Available modes: '.implode(', ', array_keys($presets)));
            return 1;
        }
        
        $this->trackFocusUsage($mode, 'skip');
        
        $emoji = $this->getFocusEmoji($mode);
        $this->info("📝 Recorded skip for {$emoji} {$mode} mode");
        $this->line('💡 This helps improve recommendations over time');
        
        // Show alternative suggestion if available
        $recommended = $this->getRecommendedFocusMode();
        if ($recommended && $recommended !== $mode) {
            $recEmoji = $this->getFocusEmoji($recommended);
            $this->line("🌟 Try {$recEmoji} {$recommended} mode instead?");
        }
        
        return 0;
    }
}
