<?php

namespace Conduit\Spotify\Concerns;

use Conduit\Spotify\Contracts\ApiInterface;

trait ProvidesLibraryOverview
{
    public function getLibraryOverview(ApiInterface $api): array
    {
        $playlists = $this->getMemoizedPlaylists($api);
        $totalTracks = 0;
        $totalDuration = 0;
        $uniqueArtists = [];

        foreach ($playlists as $playlist) {
            $trackCount = $playlist['tracks']['total'] ?? 0;
            $totalTracks += $trackCount;
            
            // Estimate duration (3.5 minutes average per track)
            $totalDuration += $trackCount * 3.5;
        }

        return [
            'total_playlists' => count($playlists),
            'total_tracks' => $totalTracks,
            'estimated_hours' => round($totalDuration / 60, 1),
            'estimated_days' => round($totalDuration / 60 / 24, 1),
            'avg_tracks_per_playlist' => round($totalTracks / count($playlists), 1),
        ];
    }

    public function getPlaylistBreakdown(ApiInterface $api): array
    {
        $playlists = $this->getMemoizedPlaylists($api);
        $breakdown = [];
        
        foreach ($playlists as $playlist) {
            $trackCount = $playlist['tracks']['total'] ?? 0;
            $breakdown[] = [
                'name' => $playlist['name'],
                'track_count' => $trackCount,
                'public' => $playlist['public'] ?? false,
                'collaborative' => $playlist['collaborative'] ?? false,
                'estimated_minutes' => round($trackCount * 3.5, 0),
            ];
        }

        // Sort by track count descending
        usort($breakdown, fn($a, $b) => $b['track_count'] <=> $a['track_count']);

        return [
            'playlists' => $breakdown,
            'largest_playlist' => $breakdown[0] ?? null,
            'smallest_playlist' => end($breakdown) ?: null,
        ];
    }

    public function getCollectionHealth(ApiInterface $api): array
    {
        $playlists = $this->getMemoizedPlaylists($api);
        $totalTracks = 0;
        $emptyPlaylists = 0;
        $largePlaylists = 0;
        
        foreach ($playlists as $playlist) {
            $trackCount = $playlist['tracks']['total'] ?? 0;
            $totalTracks += $trackCount;
            
            if ($trackCount === 0) {
                $emptyPlaylists++;
            } elseif ($trackCount > 100) {
                $largePlaylists++;
            }
        }

        $avgSize = count($playlists) > 0 ? $totalTracks / count($playlists) : 0;
        
        $healthScore = 100;
        if ($emptyPlaylists > 0) $healthScore -= ($emptyPlaylists * 10);
        if ($largePlaylists > count($playlists) / 2) $healthScore -= 20;
        
        return [
            'health_score' => max(0, $healthScore),
            'empty_playlists' => $emptyPlaylists,
            'oversized_playlists' => $largePlaylists,
            'average_size' => round($avgSize, 1),
            'recommendations' => $this->getHealthRecommendations($emptyPlaylists, $largePlaylists, $avgSize),
        ];
    }

    private function getHealthRecommendations(int $empty, int $large, float $avgSize): array
    {
        $recommendations = [];
        
        if ($empty > 0) {
            $recommendations[] = "Consider removing {$empty} empty playlist(s) to clean up your library";
        }
        
        if ($large > 2) {
            $recommendations[] = "You have {$large} large playlists - consider splitting them by mood/genre";
        }
        
        if ($avgSize < 10) {
            $recommendations[] = "Your playlists are quite small - consider consolidating similar ones";
        }
        
        if ($avgSize > 50) {
            $recommendations[] = "Your playlists are quite large - consider creating more focused collections";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Your music library looks well-organized!";
        }
        
        return $recommendations;
    }
}