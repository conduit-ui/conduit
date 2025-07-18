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

        // TODO: Deep dive into tracks to get artist frequency
        $this->line('Coming soon: Artist frequency analysis across all playlists!');

        return 0;
    }

    private function analyzeGenres(ApiInterface $api): int
    {
        $this->info('🎵 GENRE BREAKDOWN');
        $this->newLine();

        $this->line('Coming soon: Genre analysis across your music library!');

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

    private function fullAnalysis(ApiInterface $api): int
    {
        $this->line('🚀 <options=bold>FULL SPOTIFY ANALYTICS</options> 🚀');
        $this->newLine();

        $this->powerHourAnalysis($api);

        return 0;
    }
}
