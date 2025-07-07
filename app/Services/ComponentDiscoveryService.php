<?php

namespace App\Services;

use JordanPartridge\GithubClient\GithubConnector;
use JordanPartridge\GithubClient\Enums\Direction;

/**
 * Service for discovering Conduit components via GitHub search
 * 
 * This service handles the discovery of Conduit components by searching
 * GitHub repositories with the 'conduit-component' topic. It uses the 
 * github-client library's search functionality for clean API integration.
 */
class ComponentDiscoveryService
{
    public function __construct(
        private GithubConnector $github
    ) {}

    /**
     * Discover available Conduit components from GitHub
     * 
     * @return array Array of component data with name, description, URL, etc.
     * @throws \Exception When GitHub API fails
     */
    public function discoverComponents(): array
    {
        try {
            // Use the proper github-client search functionality
            $searchResults = $this->github->repos()->search(
                query: 'topic:conduit-component',
                sort: 'updated',
                order: Direction::DESC,
                per_page: 50
            );

            // Filter out archived and disabled repositories
            $activeRepos = array_filter($searchResults->items, function ($repo) {
                return !$repo->archived && !$repo->disabled;
            });

            // Transform RepoData objects to Conduit component format
            return array_map(function ($repo) {
                return [
                    'name' => $repo->name,
                    'full_name' => $repo->full_name,
                    'description' => $repo->description ?? 'No description available',
                    'url' => $repo->html_url,
                    'topics' => $repo->topics ?? [],
                    'updated_at' => $repo->updated_at,
                    'stars' => $repo->stargazers_count ?? 0,
                    'language' => $repo->language ?? 'Unknown',
                    'license' => $repo->license?->name ?? 'No license',
                ];
            }, array_values($activeRepos));

        } catch (\Exception $e) {
            throw new \Exception("Failed to discover components: " . $e->getMessage(), 0, $e);
        }
    }
}