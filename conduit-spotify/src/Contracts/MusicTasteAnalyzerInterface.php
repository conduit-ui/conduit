<?php

namespace Conduit\Spotify\Contracts;

interface MusicTasteAnalyzerInterface
{
    /**
     * Get detailed genre profile with percentages and preferences.
     */
    public function getGenreProfile(ApiInterface $api): array;

    /**
     * Analyze listening evolution over time.
     */
    public function getListeningEvolution(ApiInterface $api): array;

    /**
     * Get musical taste characteristics vector.
     */
    public function getTasteVector(ApiInterface $api): array;
}