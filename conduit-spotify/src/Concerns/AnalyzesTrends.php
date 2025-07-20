<?php

namespace Conduit\Spotify\Concerns;

use Conduit\Spotify\Contracts\ApiInterface;

trait AnalyzesTrends
{
    public function getHeatingUpTracks(ApiInterface $api): array
    {
        // Mock trending analysis - would need play count history
        return [
            'message' => 'Trend analysis requires recent listening data',
            'placeholder_trends' => [
                'Recently added tracks to playlists show increased interest',
                'Tracks in multiple playlists indicate rising preference',
            ],
        ];
    }

    public function getCoolingDownTracks(ApiInterface $api): array
    {
        return [
            'message' => 'Cooling trend analysis requires historical play data',
            'suggestion' => 'Consider removing tracks not played in 6+ months',
        ];
    }

    public function getTrendingArtists(ApiInterface $api): array
    {
        $playlists = $api->getUserPlaylists(50);
        $artistFrequency = [];
        $recentArtists = [];

        foreach ($playlists as $playlist) {
            // Check playlist creation/modification dates for recency
            $isRecent = strtotime($playlist['collaborative'] ?? 'now') > strtotime('-3 months');
            
            $tracks = $api->getPlaylistTracks($playlist['id']);
            foreach ($tracks as $track) {
                if (!isset($track['track']['artists'][0])) continue;
                
                $artist = $track['track']['artists'][0]['name'];
                $artistFrequency[$artist] = ($artistFrequency[$artist] ?? 0) + 1;
                
                if ($isRecent) {
                    $recentArtists[$artist] = ($recentArtists[$artist] ?? 0) + 1;
                }
            }
        }

        arsort($artistFrequency);
        arsort($recentArtists);

        return [
            'trending_artists' => array_slice(array_keys($recentArtists), 0, 10),
            'all_time_favorites' => array_slice(array_keys($artistFrequency), 0, 10),
            'momentum_score' => count($recentArtists),
        ];
    }

    public function getPlaylistMomentum(ApiInterface $api): array
    {
        $playlists = $api->getUserPlaylists(50);
        $playlistActivity = [];

        foreach ($playlists as $playlist) {
            $trackCount = $playlist['tracks']['total'] ?? 0;
            $isPublic = $playlist['public'] ?? false;
            
            $momentum = $trackCount * ($isPublic ? 1.2 : 1.0);
            
            $playlistActivity[] = [
                'name' => $playlist['name'],
                'track_count' => $trackCount,
                'momentum_score' => $momentum,
                'public' => $isPublic,
            ];
        }

        usort($playlistActivity, fn($a, $b) => $b['momentum_score'] <=> $a['momentum_score']);

        return [
            'hot_playlists' => array_slice($playlistActivity, 0, 5),
            'total_playlists' => count($playlistActivity),
        ];
    }
}