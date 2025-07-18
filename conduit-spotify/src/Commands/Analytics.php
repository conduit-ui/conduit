<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;

class Analytics extends Command
{
    protected $signature = 'spotify:analytics 
                           {--artists : Show top artists across playlists}
                           {--genres : Show genre breakdown}
                           {--duplicates : Find duplicate tracks}
                           {--comfort-adjacent : Find songs that vibe with your comfort songs}
                           {--high-density : Create playlist from highest comfort density playlists}
                           {--work-flow : Create coding/work energy playlist}
                           {--power-hour : Ultimate power hour analysis}';

    protected $description = '🚀 POWER HOUR playlist analytics that will blow your mind';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: conduit spotify:login');

            return 1;
        }

        if ($this->option('power-hour')) {
            return $this->powerHourAnalysis($api);
        }

        if ($this->option('artists')) {
            return $this->analyzeArtists($api);
        }

        if ($this->option('genres')) {
            return $this->analyzeGenres($api);
        }

        if ($this->option('duplicates')) {
            return $this->findDuplicates($api);
        }

        if ($this->option('comfort-adjacent')) {
            return $this->findComfortAdjacent($api);
        }

        if ($this->option('high-density')) {
            return $this->createHighDensityPlaylist($api);
        }

        if ($this->option('work-flow')) {
            return $this->createWorkFlowPlaylist($api);
        }

        // Default: show everything
        return $this->fullAnalysis($api);
    }

    private function powerHourAnalysis(ApiInterface $api): int
    {
        $this->info('🚀 POWER HOUR PLAYLIST ANALYSIS 🚀');
        $this->newLine();

        $playlists = $api->getUserPlaylists(50);

        $this->line('<options=bold>📊 YOUR MUSIC EMPIRE:</options>');
        $this->line('   📁 Total Playlists: <info>'.count($playlists).'</info>');

        $totalTracks = 0;
        $artists = [];
        $genres = [];
        $duplicates = [];

        $this->newLine();
        $this->line('<options=bold>🎵 PLAYLIST BREAKDOWN:</options>');

        foreach ($playlists as $playlist) {
            $trackCount = $playlist['tracks']['total'] ?? 0;
            $totalTracks += $trackCount;

            $this->line("   • <info>{$playlist['name']}</info> - <comment>{$trackCount} tracks</comment>");
        }

        $this->newLine();
        $this->line('<options=bold>🔥 EPIC STATS:</options>');
        $this->line("   🎶 Total Tracks: <info>{$totalTracks}</info>");
        $this->line('   ⏱️  Days of Music: <info>'.round($totalTracks * 3.5 / 60 / 24, 1).'</info>');
        $this->line('   🎧 Hours to Listen All: <info>'.round($totalTracks * 3.5 / 60, 1).'</info>');

        $this->newLine();
        $this->line("🚀 <options=bold>YOU'RE A MUSIC LEGEND!</options> 🚀");

        return 0;
    }

    private function analyzeArtists(ApiInterface $api): int
    {
        $this->info('🎤 TOP ARTISTS ACROSS YOUR PLAYLISTS');
        $this->newLine();

        $playlists = $api->getUserPlaylists(50);
        $artistFrequency = [];
        $artistTracks = [];

        $this->task('Analyzing artist frequency across playlists', function () use ($api, $playlists, &$artistFrequency, &$artistTracks) {
            foreach ($playlists as $playlist) {
                $tracks = $api->getPlaylistTracks($playlist['id']);

                foreach ($tracks as $track) {
                    $artistName = $track['track']['artists'][0]['name'] ?? 'Unknown';
                    $trackName = $track['track']['name'] ?? 'Unknown';
                    $trackUri = $track['track']['uri'] ?? null;

                    if (! $trackUri) {
                        continue;
                    }

                    $artistFrequency[$artistName] = ($artistFrequency[$artistName] ?? 0) + 1;

                    if (! isset($artistTracks[$artistName])) {
                        $artistTracks[$artistName] = [];
                    }
                    $artistTracks[$artistName][] = [
                        'name' => $trackName,
                        'uri' => $trackUri,
                    ];
                }
            }

            return true;
        });

        // Sort by frequency
        arsort($artistFrequency);

        $this->info('🔥 YOUR TOP ARTISTS:');
        $this->newLine();

        $topArtists = array_slice($artistFrequency, 0, 10, true);
        foreach ($topArtists as $artist => $count) {
            $this->line("🎤 {$artist} - {$count} tracks");
        }

        $this->info('📊 ARTIST STATS:');
        $this->line('   🎤 Total Artists: '.count($artistFrequency));
        $this->line('   🔥 Most Frequent: '.array_key_first($artistFrequency).' ('.reset($artistFrequency).' tracks)');
        $this->line('   💡 These artists dominate your playlists!');

        $this->newLine();
        if ($this->confirm('🎤 Create "Top Artists Mix" playlist with your most frequent artists?')) {
            $this->createTopArtistsPlaylist($api, $artistTracks, $artistFrequency);
        }

        return 0;
    }

    private function analyzeGenres(ApiInterface $api): int
    {
        $this->info('🎵 GENRE DNA ANALYSIS');
        $this->newLine();

        $playlists = $api->getUserPlaylists(50);
        $genreArtists = [];
        $genreTracksMap = [];

        $this->task('Analyzing genre patterns via artist data', function () use ($api, $playlists, &$genreArtists, &$genreTracksMap) {
            foreach ($playlists as $playlist) {
                $tracks = $api->getPlaylistTracks($playlist['id']);

                foreach ($tracks as $track) {
                    $artistName = $track['track']['artists'][0]['name'] ?? 'Unknown';
                    $trackUri = $track['track']['uri'] ?? null;
                    $trackName = $track['track']['name'] ?? 'Unknown';

                    if (! $trackUri) {
                        continue;
                    }

                    // Simple genre approximation based on playlist names and artist patterns
                    $inferredGenre = $this->inferGenreFromPlaylist($playlist['name'], $artistName);

                    if (! isset($genreArtists[$inferredGenre])) {
                        $genreArtists[$inferredGenre] = 0;
                        $genreTracksMap[$inferredGenre] = [];
                    }

                    $genreArtists[$inferredGenre]++;
                    $genreTracksMap[$inferredGenre][] = [
                        'name' => $trackName,
                        'artist' => $artistName,
                        'uri' => $trackUri,
                    ];
                }
            }

            return true;
        });

        // Sort by frequency
        arsort($genreArtists);

        $this->info('🔥 YOUR GENRE DNA:');
        $this->newLine();

        $topGenres = array_slice($genreArtists, 0, 8, true);
        foreach ($topGenres as $genre => $count) {
            $this->line("🎵 {$genre} - {$count} tracks");
        }

        $this->info('📊 GENRE STATS:');
        $this->line('   🎵 Total Genres: '.count($genreArtists));
        $this->line('   🔥 Dominant Genre: '.array_key_first($genreArtists).' ('.reset($genreArtists).' tracks)');
        $this->line('   💡 This is your musical DNA!');

        $this->newLine();
        if ($this->confirm('🎵 Create "Genre DNA" playlist with your musical personality?')) {
            $this->createGenreDnaPlaylist($api, $genreTracksMap, $genreArtists);
        }

        return 0;
    }

    private function findDuplicates(ApiInterface $api): int
    {
        $this->info('🔍 DUPLICATE TRACK HUNTER');
        $this->line('   Finding your comfort songs across playlists...');
        $this->newLine();

        $playlists = $api->getUserPlaylists(50);
        $trackMap = [];
        $duplicates = [];

        $this->task('Analyzing playlists for duplicates', function () use ($api, $playlists, &$trackMap, &$duplicates) {
            foreach ($playlists as $playlist) {
                $tracks = $api->getPlaylistTracks($playlist['id']);

                foreach ($tracks as $track) {
                    $trackId = $track['track']['id'] ?? null;
                    $trackName = $track['track']['name'] ?? 'Unknown';
                    $artistName = $track['track']['artists'][0]['name'] ?? 'Unknown';

                    if (! $trackId) {
                        continue;
                    }

                    if (! isset($trackMap[$trackId])) {
                        $trackMap[$trackId] = [
                            'name' => $trackName,
                            'artist' => $artistName,
                            'playlists' => [],
                        ];
                    }

                    $trackMap[$trackId]['playlists'][] = $playlist['name'];
                }
            }

            // Find duplicates (tracks in multiple playlists)
            foreach ($trackMap as $trackId => $trackData) {
                if (count($trackData['playlists']) > 1) {
                    $duplicates[] = [
                        'track' => $trackData['name'],
                        'artist' => $trackData['artist'],
                        'playlists' => $trackData['playlists'],
                        'count' => count($trackData['playlists']),
                    ];
                }
            }

            return true;
        });

        if (empty($duplicates)) {
            $this->info('🎯 No duplicate tracks found!');
            $this->line('   Each song appears in only one playlist');

            return 0;
        }

        // Sort by frequency (most duplicated first)
        usort($duplicates, fn ($a, $b) => $b['count'] <=> $a['count']);

        $this->newLine();
        $this->info('🔥 YOUR COMFORT SONGS (in multiple playlists):');
        $this->newLine();

        $topDuplicates = array_slice($duplicates, 0, 10);
        foreach ($topDuplicates as $duplicate) {
            $this->line("🎵 {$duplicate['track']} by {$duplicate['artist']}");
            $this->line("   📂 Found in {$duplicate['count']} playlists:");

            foreach ($duplicate['playlists'] as $playlist) {
                $this->line("      • {$playlist}");
            }
            $this->newLine();
        }

        $this->info('📊 DUPLICATE STATS:');
        $this->line('   🔄 Total Duplicates: '.count($duplicates));
        $this->line("   🎯 Most Duplicated: {$duplicates[0]['track']} ({$duplicates[0]['count']} playlists)");
        $this->line('   💡 These are your TRUE favorites!');

        $this->newLine();
        if ($this->confirm('🎵 Create "Comfort Songs" playlist with all your duplicates?')) {
            $this->createComfortSongsPlaylist($api, $duplicates);
        }

        return 0;
    }

    private function createComfortSongsPlaylist(ApiInterface $api, array $duplicates): void
    {
        $playlist = null;

        $this->task('Creating "Comfort Songs" playlist', function () use ($api, $duplicates, &$playlist) {
            // Get track URIs from duplicates
            $trackUris = [];
            foreach ($duplicates as $duplicate) {
                // Need to get the track URI - search for it
                $searchResults = $api->search("{$duplicate['track']} {$duplicate['artist']}", ['track'], 1);
                if (! empty($searchResults['tracks']['items'])) {
                    $track = $searchResults['tracks']['items'][0];
                    $trackUris[] = $track['uri'];
                }
            }

            if (empty($trackUris)) {
                throw new \Exception('No tracks found to add to playlist');
            }

            // Create the playlist
            $playlist = $api->createPlaylist(
                '🎵 Comfort Songs',
                'Your true favorites - songs that appear in multiple playlists. Generated by Conduit Analytics.',
                false
            );

            // Add tracks to playlist (Spotify has a limit of 100 tracks per request)
            $chunks = array_chunk($trackUris, 100);
            foreach ($chunks as $chunk) {
                $api->addTracksToPlaylist($playlist['id'], $chunk);
            }

            return true;
        });

        $this->newLine();
        $this->info('✅ "Comfort Songs" playlist created successfully!');
        $this->line('🎵 Your '.count($duplicates).' comfort songs are now in one place');
        $this->line('💡 These are the tracks you keep coming back to');
        $this->newLine();
        $this->line('🔗 Open in Spotify: https://open.spotify.com/playlist/'.$playlist['id']);
    }

    private function findComfortAdjacent(ApiInterface $api): int
    {
        $this->info('🎯 COMFORT ADJACENT ANALYZER');
        $this->line('   Finding songs that vibe with your comfort songs...');
        $this->newLine();

        // First, get all duplicates (comfort songs)
        $playlists = $api->getUserPlaylists(50);
        $trackMap = [];
        $duplicates = [];

        $this->task('Analyzing comfort songs and adjacencies', function () use ($api, $playlists, &$trackMap, &$duplicates) {
            foreach ($playlists as $playlist) {
                $tracks = $api->getPlaylistTracks($playlist['id']);

                foreach ($tracks as $track) {
                    $trackId = $track['track']['id'] ?? null;
                    $trackName = $track['track']['name'] ?? 'Unknown';
                    $artistName = $track['track']['artists'][0]['name'] ?? 'Unknown';

                    if (! $trackId) {
                        continue;
                    }

                    if (! isset($trackMap[$trackId])) {
                        $trackMap[$trackId] = [
                            'name' => $trackName,
                            'artist' => $artistName,
                            'playlists' => [],
                        ];
                    }

                    $trackMap[$trackId]['playlists'][] = $playlist['name'];
                }
            }

            // Find duplicates (comfort songs)
            foreach ($trackMap as $trackId => $trackData) {
                if (count($trackData['playlists']) > 1) {
                    $duplicates[] = [
                        'track' => $trackData['name'],
                        'artist' => $trackData['artist'],
                        'playlists' => $trackData['playlists'],
                        'count' => count($trackData['playlists']),
                    ];
                }
            }

            return true;
        });

        if (empty($duplicates)) {
            $this->info('❌ No comfort songs found. Run --duplicates first!');

            return 0;
        }

        // Find comfort-adjacent songs
        $comfortPlaylists = [];
        foreach ($duplicates as $duplicate) {
            foreach ($duplicate['playlists'] as $playlist) {
                $comfortPlaylists[$playlist] = ($comfortPlaylists[$playlist] ?? 0) + 1;
            }
        }

        // Sort playlists by comfort song density
        arsort($comfortPlaylists);

        $this->info('🔥 HIGH COMFORT PLAYLISTS:');
        foreach (array_slice($comfortPlaylists, 0, 5, true) as $playlist => $count) {
            $this->line("   📂 {$playlist} - {$count} comfort songs");
        }

        $this->newLine();

        // Find adjacent songs (songs that share playlists with comfort songs but aren't duplicates)
        $adjacentCandidates = [];
        foreach ($trackMap as $trackId => $trackData) {
            if (count($trackData['playlists']) == 1) { // Not a duplicate
                $playlist = $trackData['playlists'][0];
                // Skip the comfort songs playlist itself
                if ($playlist === '🎵 Comfort Songs') {
                    continue;
                }

                if (isset($comfortPlaylists[$playlist]) && $comfortPlaylists[$playlist] >= 2) {
                    $adjacentCandidates[] = [
                        'track' => $trackData['name'],
                        'artist' => $trackData['artist'],
                        'playlist' => $playlist,
                        'comfort_density' => $comfortPlaylists[$playlist],
                    ];
                }
            }
        }

        // Sort by comfort density
        usort($adjacentCandidates, fn ($a, $b) => $b['comfort_density'] <=> $a['comfort_density']);

        $this->info('🎵 COMFORT ADJACENT SONGS (songs in high-comfort playlists):');
        $this->newLine();

        $topAdjacent = array_slice($adjacentCandidates, 0, 10);
        foreach ($topAdjacent as $candidate) {
            $this->line("🎯 {$candidate['track']} by {$candidate['artist']}");
            $this->line("   📂 In: {$candidate['playlist']} (with {$candidate['comfort_density']} comfort songs)");
            $this->newLine();
        }

        $this->info('📊 COMFORT ADJACENT STATS:');
        $this->line('   🎯 Total Adjacent Songs: '.count($adjacentCandidates));
        $this->line('   🔥 High Comfort Playlists: '.count(array_filter($comfortPlaylists, fn ($count) => $count >= 2)));
        $this->line('   💡 These songs vibe with your comfort songs!');

        $this->newLine();
        if (! empty($adjacentCandidates) && $this->confirm('🎯 Create "Comfort Adjacent" playlist with these vibes?')) {
            $this->createComfortAdjacentPlaylist($api, $adjacentCandidates);
        }

        return 0;
    }

    private function createComfortAdjacentPlaylist(ApiInterface $api, array $adjacentCandidates): void
    {
        $playlist = null;

        $this->task('Creating "Comfort Adjacent" playlist', function () use ($api, $adjacentCandidates, &$playlist) {
            // Get track URIs from adjacent candidates (limit to top 50 for a good playlist size)
            $trackUris = [];
            $topCandidates = array_slice($adjacentCandidates, 0, 50);

            foreach ($topCandidates as $candidate) {
                // Search for the track to get its URI
                $searchResults = $api->search("{$candidate['track']} {$candidate['artist']}", ['track'], 1);
                if (! empty($searchResults['tracks']['items'])) {
                    $track = $searchResults['tracks']['items'][0];
                    $trackUris[] = $track['uri'];
                }
            }

            if (empty($trackUris)) {
                throw new \Exception('No tracks found to add to playlist');
            }

            // Create the playlist
            $playlist = $api->createPlaylist(
                '🎯 Comfort Adjacent',
                'Songs that vibe with your comfort songs but aren\'t duplicates yet. These might become your new comfort songs! Generated by Conduit Analytics.',
                false
            );

            // Add tracks to playlist
            $chunks = array_chunk($trackUris, 100);
            foreach ($chunks as $chunk) {
                $api->addTracksToPlaylist($playlist['id'], $chunk);
            }

            return true;
        });

        $this->newLine();
        $this->info('✅ "Comfort Adjacent" playlist created successfully!');
        $this->line('🎯 Your '.min(count($adjacentCandidates), 50).' comfort adjacent songs are ready to explore');
        $this->line('💡 These songs vibe with your comfort songs - they might become new favorites!');
        $this->newLine();
        $this->line('🔗 Open in Spotify: https://open.spotify.com/playlist/'.$playlist['id']);
    }

    private function createTopArtistsPlaylist(ApiInterface $api, array $artistTracks, array $artistFrequency): void
    {
        $playlist = null;

        $this->task('Creating "Top Artists Mix" playlist', function () use ($api, $artistTracks, $artistFrequency, &$playlist) {
            $trackUris = [];

            // Get top 10 artists
            $topArtists = array_slice($artistFrequency, 0, 10, true);

            foreach ($topArtists as $artist => $count) {
                // Add up to 5 tracks per top artist
                $artistTrackList = array_slice($artistTracks[$artist], 0, 5);
                foreach ($artistTrackList as $track) {
                    $trackUris[] = $track['uri'];
                }
            }

            if (empty($trackUris)) {
                throw new \Exception('No tracks found to add to playlist');
            }

            // Create the playlist
            $playlist = $api->createPlaylist(
                '🎤 Top Artists Mix',
                'Your most frequent artists across all playlists. These artists dominate your music taste! Generated by Conduit Analytics.',
                false
            );

            // Add tracks to playlist
            $chunks = array_chunk($trackUris, 100);
            foreach ($chunks as $chunk) {
                $api->addTracksToPlaylist($playlist['id'], $chunk);
            }

            return true;
        });

        $this->newLine();
        $this->info('✅ "Top Artists Mix" playlist created successfully!');
        $this->line('🎤 Your top '.min(count($artistFrequency), 10).' artists are showcased');
        $this->line('💡 These artists define your musical DNA!');
        $this->newLine();
        $this->line('🔗 Open in Spotify: https://open.spotify.com/playlist/'.$playlist['id']);
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

        // Default based on some artist patterns
        return 'Mixed';
    }

    private function createGenreDnaPlaylist(ApiInterface $api, array $genreTracksMap, array $genreArtists): void
    {
        $playlist = null;

        $this->task('Creating "Genre DNA" playlist', function () use ($api, $genreTracksMap, $genreArtists, &$playlist) {
            $trackUris = [];

            // Get top 6 genres and mix their tracks
            $topGenres = array_slice($genreArtists, 0, 6, true);

            foreach ($topGenres as $genre => $count) {
                // Add up to 8 tracks per genre
                $genreTrackList = array_slice($genreTracksMap[$genre], 0, 8);
                foreach ($genreTrackList as $track) {
                    $trackUris[] = $track['uri'];
                }
            }

            if (empty($trackUris)) {
                throw new \Exception('No tracks found to add to playlist');
            }

            // Create the playlist
            $playlist = $api->createPlaylist(
                '🎵 Genre DNA',
                'A cross-section of your musical personality across genres. This playlist represents your diverse musical DNA! Generated by Conduit Analytics.',
                false
            );

            // Add tracks to playlist
            $chunks = array_chunk($trackUris, 100);
            foreach ($chunks as $chunk) {
                $api->addTracksToPlaylist($playlist['id'], $chunk);
            }

            return true;
        });

        $this->newLine();
        $this->info('✅ "Genre DNA" playlist created successfully!');
        $this->line('🎵 Your top '.min(count($genreArtists), 6).' genres are represented');
        $this->line('💡 This is your musical personality in playlist form!');
        $this->newLine();
        $this->line('🔗 Open in Spotify: https://open.spotify.com/playlist/'.$playlist['id']);
    }

    private function createHighDensityPlaylist(ApiInterface $api): int
    {
        $this->info('🔥 HIGH COMFORT DENSITY PLAYLIST');
        $this->line('   Creating playlist from your most comfort-heavy playlists...');
        $this->newLine();

        $playlists = $api->getUserPlaylists(50);
        $trackMap = [];
        $duplicates = [];

        // Get comfort songs data
        $this->task('Analyzing comfort density', function () use ($api, $playlists, &$trackMap, &$duplicates) {
            foreach ($playlists as $playlist) {
                $tracks = $api->getPlaylistTracks($playlist['id']);

                foreach ($tracks as $track) {
                    $trackId = $track['track']['id'] ?? null;
                    $trackName = $track['track']['name'] ?? 'Unknown';
                    $artistName = $track['track']['artists'][0]['name'] ?? 'Unknown';

                    if (! $trackId) {
                        continue;
                    }

                    if (! isset($trackMap[$trackId])) {
                        $trackMap[$trackId] = [
                            'name' => $trackName,
                            'artist' => $artistName,
                            'playlists' => [],
                            'uri' => $track['track']['uri'],
                        ];
                    }

                    $trackMap[$trackId]['playlists'][] = $playlist['name'];
                }
            }

            // Find duplicates
            foreach ($trackMap as $trackId => $trackData) {
                if (count($trackData['playlists']) > 1) {
                    $duplicates[] = $trackData;
                }
            }

            return true;
        });

        // Get high density playlists
        $comfortPlaylists = [];
        foreach ($duplicates as $duplicate) {
            foreach ($duplicate['playlists'] as $playlist) {
                $comfortPlaylists[$playlist] = ($comfortPlaylists[$playlist] ?? 0) + 1;
            }
        }

        arsort($comfortPlaylists);
        $topDensityPlaylists = array_slice($comfortPlaylists, 0, 3, true);

        $this->info('🔥 HIGHEST COMFORT DENSITY PLAYLISTS:');
        foreach ($topDensityPlaylists as $playlist => $count) {
            $this->line("   📂 {$playlist} - {$count} comfort songs");
        }

        if ($this->confirm('🔥 Create "High Density" playlist from these comfort-heavy playlists?')) {
            $this->generateHighDensityPlaylist($api, $topDensityPlaylists, $playlists);
        }

        return 0;
    }

    private function createWorkFlowPlaylist(ApiInterface $api): int
    {
        $this->info('💻 WORK FLOW VIBES PLAYLIST');
        $this->line('   Creating coding energy playlist from your work playlists...');
        $this->newLine();

        $playlists = $api->getUserPlaylists(50);
        $workTracks = [];

        $this->task('Analyzing work/coding playlists', function () use ($api, $playlists, &$workTracks) {
            foreach ($playlists as $playlist) {
                $playlistLower = strtolower($playlist['name']);

                // Find work-related playlists
                $workKeywords = ['work', 'flow', 'code', 'coding', 'hacker', 'focus', 'productivity'];
                $isWorkPlaylist = false;

                foreach ($workKeywords as $keyword) {
                    if (str_contains($playlistLower, $keyword)) {
                        $isWorkPlaylist = true;
                        break;
                    }
                }

                if ($isWorkPlaylist) {
                    $tracks = $api->getPlaylistTracks($playlist['id']);

                    foreach ($tracks as $track) {
                        $trackUri = $track['track']['uri'] ?? null;
                        $trackName = $track['track']['name'] ?? 'Unknown';
                        $artistName = $track['track']['artists'][0]['name'] ?? 'Unknown';

                        if (! $trackUri) {
                            continue;
                        }

                        $workTracks[] = [
                            'name' => $trackName,
                            'artist' => $artistName,
                            'uri' => $trackUri,
                            'playlist' => $playlist['name'],
                        ];
                    }
                }
            }

            return true;
        });

        $this->info('💻 WORK FLOW TRACKS FOUND:');
        $this->line('   🎵 Total Work Tracks: '.count($workTracks));

        if (! empty($workTracks)) {
            $this->line('   📂 From playlists with work/coding vibes');

            if ($this->confirm('💻 Create "Work Flow Vibes" playlist for coding energy?')) {
                $this->generateWorkFlowPlaylist($api, $workTracks);
            }
        } else {
            $this->info('❌ No work/coding playlists found');
        }

        return 0;
    }

    private function generateHighDensityPlaylist(ApiInterface $api, array $topDensityPlaylists, array $allPlaylists): void
    {
        $playlist = null;

        $this->task('Creating "High Density" playlist', function () use ($api, $topDensityPlaylists, $allPlaylists, &$playlist) {
            $trackUris = [];

            foreach ($topDensityPlaylists as $playlistName => $count) {
                // Find the playlist and get its tracks
                foreach ($allPlaylists as $playlistData) {
                    if ($playlistData['name'] === $playlistName) {
                        $tracks = $api->getPlaylistTracks($playlistData['id']);

                        // Add up to 15 tracks from each high-density playlist
                        $trackCount = 0;
                        foreach ($tracks as $track) {
                            if ($trackCount >= 15) {
                                break;
                            }

                            $trackUri = $track['track']['uri'] ?? null;
                            if ($trackUri) {
                                $trackUris[] = $trackUri;
                                $trackCount++;
                            }
                        }
                        break;
                    }
                }
            }

            if (empty($trackUris)) {
                throw new \Exception('No tracks found to add to playlist');
            }

            // Create the playlist
            $playlist = $api->createPlaylist(
                '🔥 High Density',
                'Tracks from your most comfort-heavy playlists. These playlists have the highest concentration of your favorite songs! Generated by Conduit Analytics.',
                false
            );

            // Add tracks to playlist
            $chunks = array_chunk($trackUris, 100);
            foreach ($chunks as $chunk) {
                $api->addTracksToPlaylist($playlist['id'], $chunk);
            }

            return true;
        });

        $this->newLine();
        $this->info('✅ "High Density" playlist created successfully!');
        $this->line('🔥 Your highest comfort density playlists are combined');
        $this->line('💡 This is where your best music concentrates!');
        $this->newLine();
        $this->line('🔗 Open in Spotify: https://open.spotify.com/playlist/'.$playlist['id']);
    }

    private function generateWorkFlowPlaylist(ApiInterface $api, array $workTracks): void
    {
        $playlist = null;

        $this->task('Creating "Work Flow Vibes" playlist', function () use ($api, $workTracks, &$playlist) {
            // Limit to 50 tracks for a good work playlist
            $selectedTracks = array_slice($workTracks, 0, 50);
            $trackUris = array_column($selectedTracks, 'uri');

            if (empty($trackUris)) {
                throw new \Exception('No tracks found to add to playlist');
            }

            // Create the playlist
            $playlist = $api->createPlaylist(
                '💻 Work Flow Vibes',
                'Coding energy and focus tracks from your work playlists. Perfect for deep work sessions and productivity! Generated by Conduit Analytics.',
                false
            );

            // Add tracks to playlist
            $chunks = array_chunk($trackUris, 100);
            foreach ($chunks as $chunk) {
                $api->addTracksToPlaylist($playlist['id'], $chunk);
            }

            return true;
        });

        $this->newLine();
        $this->info('✅ "Work Flow Vibes" playlist created successfully!');
        $this->line('💻 Your coding energy is ready to deploy');
        $this->line('💡 Perfect for deep work sessions!');
        $this->newLine();
        $this->line('🔗 Open in Spotify: https://open.spotify.com/playlist/'.$playlist['id']);
    }

    private function fullAnalysis(ApiInterface $api): int
    {
        $this->line('🚀 <options=bold>FULL SPOTIFY ANALYTICS</options> 🚀');
        $this->newLine();

        $this->powerHourAnalysis($api);

        return 0;
    }
}
