<?php

namespace Conduit\Spotify\Contracts;

interface ArtistAnalyzerInterface
{
    /**
     * Analyze artist frequency across playlists.
     */
    public function analyzeArtists(ApiInterface $api): array;

    /**
     * Create a playlist from top artists.
     */
    public function createTopArtistsPlaylist(ApiInterface $api, array $artistTracks, array $artistFrequency): void;
}