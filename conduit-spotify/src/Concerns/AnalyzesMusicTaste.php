<?php

namespace Conduit\Spotify\Concerns;

use Conduit\Spotify\Contracts\ApiInterface;

trait AnalyzesMusicTaste
{
    public function getGenreProfile(ApiInterface $api): array
    {
        $allTracks = $this->getMemoizedAllTracks($api);
        $genreData = [];
        $totalTracks = 0;

        foreach ($allTracks as $trackData) {
            $track = $trackData['track'];
            if (!isset($track['artists'][0]['id'])) continue;
            
            $artistId = $track['artists'][0]['id'];
            if (!$artistId) continue;
            
            $artist = $this->getMemoizedArtist($api, $artistId);
            $genres = $artist['genres'] ?? [];
            
            foreach ($genres as $genre) {
                $genreData[$genre] = ($genreData[$genre] ?? 0) + 1;
                $totalTracks++;
            }
        }

        // Calculate percentages
        $genreProfile = [];
        foreach ($genreData as $genre => $count) {
            $genreProfile[] = [
                'genre' => $genre,
                'count' => $count,
                'percentage' => round(($count / $totalTracks) * 100, 1),
            ];
        }

        // Sort by count descending
        usort($genreProfile, fn($a, $b) => $b['count'] <=> $a['count']);

        return [
            'total_tracks' => $totalTracks,
            'genre_diversity' => count($genreProfile),
            'top_genres' => array_slice($genreProfile, 0, 5),
            'all_genres' => $genreProfile,
        ];
    }

    public function getListeningEvolution(ApiInterface $api): array
    {
        // For now, return placeholder - would need listening history from Spotify API
        return [
            'message' => 'Listening evolution analysis requires recent tracks history',
            'suggestion' => 'Enable listening history in Spotify settings for better insights',
        ];
    }

    public function getTasteVector(ApiInterface $api): array
    {
        $genreProfile = $this->getGenreProfile($api);
        $topGenres = array_slice($genreProfile['top_genres'], 0, 3);
        
        $complexity = $genreProfile['genre_diversity'] > 10 ? 'High' : 
                     ($genreProfile['genre_diversity'] > 5 ? 'Medium' : 'Low');

        return [
            'primary_genres' => array_column($topGenres, 'genre'),
            'taste_complexity' => $complexity,
            'genre_diversity_score' => $genreProfile['genre_diversity'],
            'dominant_percentage' => $topGenres[0]['percentage'] ?? 0,
        ];
    }
}