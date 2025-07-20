<?php

namespace Conduit\Spotify\Contracts;

interface TrendAnalyzerInterface
{
    /**
     * Get tracks that are heating up in rotation.
     */
    public function getHeatingUpTracks(ApiInterface $api): array;

    /**
     * Get tracks losing momentum.
     */
    public function getCoolingDownTracks(ApiInterface $api): array;

    /**
     * Get artists gaining momentum in your library.
     */
    public function getTrendingArtists(ApiInterface $api): array;

    /**
     * Get playlists with increasing activity.
     */
    public function getPlaylistMomentum(ApiInterface $api): array;
}