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

    /** @var array Cache for API responses */
    private array $memoizedData = [];

    /** @var ApiInterface Current API instance for cache validation */
    private ?ApiInterface $currentApi = null;

    public function runIntelligentAnalysis(ApiInterface $api): array
    {
        $this->initializeCache($api);
        
        return [
            'library_overview' => $this->getLibraryOverview($api),
            'music_taste' => $this->getGenreProfile($api),
            'trending_artists' => $this->getTrendingArtists($api),
            'collection_health' => $this->getCollectionHealth($api),
            'taste_vector' => $this->getTasteVector($api),
        ];
    }

    /**
     * Initialize cache for the current API session
     */
    private function initializeCache(ApiInterface $api): void
    {
        if ($this->currentApi !== $api) {
            $this->memoizedData = [];
            $this->currentApi = $api;
        }
    }

    /**
     * Get memoized playlists data
     */
    protected function getMemoizedPlaylists(ApiInterface $api): array
    {
        $this->initializeCache($api);
        
        if (!isset($this->memoizedData['playlists'])) {
            $this->memoizedData['playlists'] = $api->getUserPlaylists(50);
        }
        
        return $this->memoizedData['playlists'];
    }

    /**
     * Get memoized playlist tracks for a specific playlist
     */
    protected function getMemoizedPlaylistTracks(ApiInterface $api, string $playlistId): array
    {
        $this->initializeCache($api);
        
        $cacheKey = "playlist_tracks_{$playlistId}";
        if (!isset($this->memoizedData[$cacheKey])) {
            $this->memoizedData[$cacheKey] = $api->getPlaylistTracks($playlistId);
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Get all tracks from all playlists (expensive operation, heavily memoized)
     */
    protected function getMemoizedAllTracks(ApiInterface $api): array
    {
        $this->initializeCache($api);
        
        if (!isset($this->memoizedData['all_tracks'])) {
            $allTracks = [];
            $playlists = $this->getMemoizedPlaylists($api);
            
            foreach ($playlists as $playlist) {
                $tracks = $this->getMemoizedPlaylistTracks($api, $playlist['id']);
                foreach ($tracks as $track) {
                    $allTracks[] = [
                        'track' => $track['track'],
                        'playlist_name' => $playlist['name'],
                        'playlist_id' => $playlist['id'],
                    ];
                }
            }
            
            $this->memoizedData['all_tracks'] = $allTracks;
        }
        
        return $this->memoizedData['all_tracks'];
    }

    /**
     * Get memoized artist data
     */
    protected function getMemoizedArtist(ApiInterface $api, string $artistId): array
    {
        $this->initializeCache($api);
        
        $cacheKey = "artist_{$artistId}";
        if (!isset($this->memoizedData[$cacheKey])) {
            $this->memoizedData[$cacheKey] = $api->getArtist($artistId);
        }
        
        return $this->memoizedData[$cacheKey];
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