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
        $playlists = $this->getMemoizedPlaylists($api);
        $allTracks = $this->getMemoizedAllTracks($api);
        $artistFrequency = [];
        $recentArtists = [];

        // Create lookup for recent playlists
        $recentPlaylistIds = [];
        foreach ($playlists as $playlist) {
            $isRecent = strtotime($playlist['collaborative'] ?? 'now') > strtotime('-3 months');
            if ($isRecent) {
                $recentPlaylistIds[] = $playlist['id'];
            }
        }

        foreach ($allTracks as $trackData) {
            $track = $trackData['track'];
            if (! isset($track['artists'][0])) {
                continue;
            }

            $artist = $track['artists'][0]['name'];
            $artistFrequency[$artist] = ($artistFrequency[$artist] ?? 0) + 1;

            if (in_array($trackData['playlist_id'], $recentPlaylistIds)) {
                $recentArtists[$artist] = ($recentArtists[$artist] ?? 0) + 1;
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
        $playlists = $this->getMemoizedPlaylists($api);
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

        usort($playlistActivity, fn ($a, $b) => $b['momentum_score'] <=> $a['momentum_score']);

        return [
            'hot_playlists' => array_slice($playlistActivity, 0, 5),
            'total_playlists' => count($playlistActivity),
        ];
    }
}
