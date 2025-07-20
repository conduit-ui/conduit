<?php

namespace Conduit\Spotify\Services;

use Conduit\Spotify\Concerns\AnalyzesArtists;
use Conduit\Spotify\Concerns\AnalyzesMusicTaste;
use Conduit\Spotify\Concerns\AnalyzesTrends;
use Conduit\Spotify\Concerns\ProvidesLibraryOverview;
use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\IntelligentAnalyticsInterface;

class IntelligentAnalyticsService implements IntelligentAnalyticsInterface
{
    use AnalyzesArtists;
    use AnalyzesMusicTaste;
    use AnalyzesTrends;
    use ProvidesLibraryOverview;

    public function runIntelligentAnalysis(ApiInterface $api): array
    {
        return [
            'library_overview' => $this->getLibraryOverview($api),
            'music_taste' => $this->getGenreProfile($api),
            'trending_artists' => $this->getTrendingArtists($api),
            'collection_health' => $this->getCollectionHealth($api),
            'taste_vector' => $this->getTasteVector($api),
        ];
    }

    public function getPersonalizedInsights(ApiInterface $api): array
    {
        $tasteVector = $this->getTasteVector($api);
        $health = $this->getCollectionHealth($api);
        $trending = $this->getTrendingArtists($api);
        
        $insights = [];
        
        // Taste complexity insights
        $complexity = $tasteVector['taste_complexity'];
        if ($complexity === 'High') {
            $insights[] = "🎵 You have diverse musical taste spanning {$tasteVector['genre_diversity_score']} genres";
        } elseif ($complexity === 'Low') {
            $insights[] = "🎯 You have focused taste - consider exploring new genres for variety";
        }
        
        // Dominant genre insights
        $dominance = $tasteVector['dominant_percentage'];
        if ($dominance > 50) {
            $primaryGenre = $tasteVector['primary_genres'][0] ?? 'Unknown';
            $insights[] = "🔥 {$primaryGenre} dominates your library ({$dominance}%) - you know what you like!";
        }
        
        // Collection health insights
        if ($health['health_score'] < 70) {
            $insights[] = "🧹 Your library could use some organization - check the health recommendations";
        } elseif ($health['health_score'] > 90) {
            $insights[] = "✨ Your music library is well-organized and healthy!";
        }
        
        // Trending insights
        $momentumScore = $trending['momentum_score'];
        if ($momentumScore > 20) {
            $insights[] = "📈 You're actively discovering new artists - great musical exploration!";
        } elseif ($momentumScore < 5) {
            $insights[] = "💡 Consider exploring new artists to refresh your library";
        }
        
        return [
            'insights' => $insights,
            'recommendations' => $this->generateSmartRecommendations($tasteVector, $health, $trending),
        ];
    }

    private function generateSmartRecommendations(array $tasteVector, array $health, array $trending): array
    {
        $recommendations = [];
        
        // Genre expansion recommendations
        if ($tasteVector['genre_diversity_score'] < 5) {
            $primaryGenre = $tasteVector['primary_genres'][0] ?? null;
            if ($primaryGenre) {
                $recommendations[] = "Explore genres related to {$primaryGenre}";
            }
        }
        
        // Playlist optimization
        if ($health['oversized_playlists'] > 0) {
            $recommendations[] = "Split large playlists into themed collections for easier navigation";
        }
        
        if ($health['empty_playlists'] > 0) {
            $recommendations[] = "Remove empty playlists to declutter your library";
        }
        
        // Discovery recommendations
        if (count($trending['trending_artists']) > 0) {
            $topTrending = $trending['trending_artists'][0];
            $recommendations[] = "Explore more tracks from {$topTrending} - they're trending in your library";
        }
        
        return $recommendations;
    }
}