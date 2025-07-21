<?php

namespace Conduit\Spotify\Contracts;

interface LibraryOverviewInterface
{
    /**
     * Get comprehensive library statistics.
     */
    public function getLibraryOverview(ApiInterface $api): array;

    /**
     * Get playlist breakdown with useful metrics.
     */
    public function getPlaylistBreakdown(ApiInterface $api): array;

    /**
     * Get collection health metrics.
     */
    public function getCollectionHealth(ApiInterface $api): array;
}
