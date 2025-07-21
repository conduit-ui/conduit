<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Conduit\Spotify\Services\IntelligentAnalyticsService;
use Illuminate\Console\Command;

class Analytics extends Command
{
    protected $signature = 'spotify:analytics 
                           {--overview : Show comprehensive library overview}
                           {--taste : Analyze your music taste profile}
                           {--trends : Show trending artists and patterns}
                           {--artists : Analyze artist frequency}
                           {--health : Check collection health}
                           {--insights : Get personalized insights}
                           {--all : Run complete intelligent analysis}';

    protected $description = 'Intelligent Spotify analytics with actionable insights';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: conduit spotify:login');

            return 1;
        }

        $analytics = new IntelligentAnalyticsService;

        try {
            if ($this->option('overview')) {
                return $this->showOverview($analytics, $api);
            }

            if ($this->option('taste')) {
                return $this->showMusicTaste($analytics, $api);
            }

            if ($this->option('trends')) {
                return $this->showTrends($analytics, $api);
            }

            if ($this->option('artists')) {
                return $this->showArtists($analytics, $api);
            }

            if ($this->option('health')) {
                return $this->showHealth($analytics, $api);
            }

            if ($this->option('insights')) {
                return $this->showInsights($analytics, $api);
            }

            if ($this->option('all')) {
                return $this->showCompleteAnalysis($analytics, $api);
            }

            // Default: show overview
            return $this->showOverview($analytics, $api);

        } catch (\Exception $e) {
            $this->error("❌ Analysis failed: {$e->getMessage()}");

            return 1;
        }
    }

    private function showOverview(IntelligentAnalyticsService $analytics, ApiInterface $api): int
    {
        $this->info('📊 Library Overview');
        $this->newLine();

        $overview = $analytics->getLibraryOverview($api);

        $this->line("📁 Total Playlists: <info>{$overview['total_playlists']}</info>");
        $this->line("🎵 Total Tracks: <info>{$overview['total_tracks']}</info>");
        $this->line("⏱️  Estimated Hours: <info>{$overview['estimated_hours']}</info>");
        $this->line("📊 Avg Tracks/Playlist: <info>{$overview['avg_tracks_per_playlist']}</info>");

        $breakdown = $analytics->getPlaylistBreakdown($api);
        $largest = $breakdown['largest_playlist'];

        if ($largest) {
            $this->newLine();
            $this->line("🏆 Largest Playlist: <info>{$largest['name']}</info> ({$largest['track_count']} tracks)");
        }

        return 0;
    }

    private function showMusicTaste(IntelligentAnalyticsService $analytics, ApiInterface $api): int
    {
        $this->info('🎵 Music Taste Profile');
        $this->newLine();

        $taste = $analytics->getGenreProfile($api);
        $vector = $analytics->getTasteVector($api);

        $this->line("🎯 Genre Diversity: <info>{$vector['taste_complexity']}</info> ({$taste['genre_diversity']} genres)");
        $dominantGenre = $vector['primary_genres'][0] ?? 'Unknown';
        $this->line("🔥 Dominant Genre: <info>{$dominantGenre}</info> ({$vector['dominant_percentage']}%)");

        $this->newLine();
        $this->line('<options=bold>Top Genres:</options>');
        foreach (array_slice($taste['top_genres'], 0, 5) as $genre) {
            $this->line("   • {$genre['genre']}: {$genre['percentage']}% ({$genre['count']} tracks)");
        }

        return 0;
    }

    private function showTrends(IntelligentAnalyticsService $analytics, ApiInterface $api): int
    {
        $this->info('📈 Trending Analysis');
        $this->newLine();

        $trends = $analytics->getTrendingArtists($api);

        $this->line('<options=bold>Currently Trending Artists:</options>');
        foreach (array_slice($trends['trending_artists'], 0, 10) as $index => $artist) {
            $this->line('   '.($index + 1).". {$artist}");
        }

        $momentum = $analytics->getPlaylistMomentum($api);
        $this->newLine();
        $this->line('<options=bold>Hot Playlists:</options>');
        foreach ($momentum['hot_playlists'] as $playlist) {
            $this->line("   🔥 {$playlist['name']} ({$playlist['track_count']} tracks)");
        }

        return 0;
    }

    private function showArtists(IntelligentAnalyticsService $analytics, ApiInterface $api): int
    {
        $this->info('🎤 Artist Analysis');
        $this->newLine();

        $artists = $analytics->analyzeArtists($api);

        $this->line("🎵 Total Artists: <info>{$artists['total_artists']}</info>");
        $this->line("🏆 Most Frequent: <info>{$artists['most_frequent_artist']}</info> ({$artists['most_frequent_count']} tracks)");

        $this->newLine();
        $this->line('<options=bold>Top 10 Artists:</options>');
        $topArtists = array_slice($artists['artist_frequency'], 0, 10, true);
        foreach ($topArtists as $artist => $count) {
            $this->line("   • {$artist}: {$count} tracks");
        }

        return 0;
    }

    private function showHealth(IntelligentAnalyticsService $analytics, ApiInterface $api): int
    {
        $this->info('🏥 Collection Health');
        $this->newLine();

        $health = $analytics->getCollectionHealth($api);

        $score = $health['health_score'];
        $color = $score > 80 ? 'green' : ($score > 60 ? 'yellow' : 'red');

        $this->line("📊 Health Score: <fg={$color}>{$score}/100</fg={$color}>");
        $this->line("📭 Empty Playlists: <info>{$health['empty_playlists']}</info>");
        $this->line("📚 Large Playlists: <info>{$health['oversized_playlists']}</info>");
        $this->line("📏 Average Size: <info>{$health['average_size']}</info> tracks");

        $this->newLine();
        $this->line('<options=bold>Recommendations:</options>');
        foreach ($health['recommendations'] as $recommendation) {
            $this->line("   💡 {$recommendation}");
        }

        return 0;
    }

    private function showInsights(IntelligentAnalyticsService $analytics, ApiInterface $api): int
    {
        $this->info('🧠 Personalized Insights');
        $this->newLine();

        $insights = $analytics->getPersonalizedInsights($api);

        foreach ($insights['insights'] as $insight) {
            $this->line("   {$insight}");
        }

        if (! empty($insights['recommendations'])) {
            $this->newLine();
            $this->line('<options=bold>Smart Recommendations:</options>');
            foreach ($insights['recommendations'] as $recommendation) {
                $this->line("   💡 {$recommendation}");
            }
        }

        return 0;
    }

    private function showCompleteAnalysis(IntelligentAnalyticsService $analytics, ApiInterface $api): int
    {
        $this->info('🚀 Complete Intelligent Analysis');
        $this->newLine();

        $this->showOverview($analytics, $api);
        $this->newLine();
        $this->showMusicTaste($analytics, $api);
        $this->newLine();
        $this->showTrends($analytics, $api);
        $this->newLine();
        $this->showHealth($analytics, $api);
        $this->newLine();
        $this->showInsights($analytics, $api);

        return 0;
    }
}
