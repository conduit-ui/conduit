<?php

namespace Conduit\Spotify\Contracts;

/**
 * Main analytics interface that extends all intelligent analysis capabilities.
 */
interface IntelligentAnalyticsInterface extends 
    MusicTasteAnalyzerInterface,
    TrendAnalyzerInterface,
    LibraryOverviewInterface,
    ArtistAnalyzerInterface
{
    /**
     * Run comprehensive intelligent analysis.
     */
    public function runIntelligentAnalysis(ApiInterface $api): array;

    /**
     * Get personalized insights based on listening patterns.
     */
    public function getPersonalizedInsights(ApiInterface $api): array;
}