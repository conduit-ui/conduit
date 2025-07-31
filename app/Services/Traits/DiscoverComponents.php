<?php

namespace App\Services\Traits;

use GuzzleHttp\Client;

/**
 * Trait for component discovery capabilities
 */
trait DiscoverComponents
{
    public function discover(): array
    {
        $topic = 'conduit-component';

        try {
            $client = new Client([
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Conduit/1.0',
                    'Accept' => 'application/vnd.github.v3+json',
                ],
            ]);

            $response = $client->get('https://api.github.com/search/repositories', [
                'query' => [
                    'q' => "topic:{$topic}",
                    'sort' => 'updated',
                    'order' => 'desc',
                    'per_page' => 50,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['items'])) {
                return [];
            }

            return collect($data['items'])
                ->filter(function ($repo) {
                    return !($repo['archived'] ?? false) && !($repo['disabled'] ?? false);
                })
                ->map(function ($repo) {
                    $displayName = $repo['name'];
                    if (str_starts_with($displayName, 'conduit-')) {
                        $displayName = substr($displayName, 8);
                    }

                    return [
                        'name' => $displayName,
                        'repo_name' => $repo['name'],
                        'full_name' => $repo['full_name'],
                        'description' => $repo['description'] ?? 'No description available',
                        'url' => $repo['html_url'],
                        'topics' => $repo['topics'] ?? [],
                        'updated_at' => $repo['updated_at'],
                        'stars' => $repo['stargazers_count'] ?? 0,
                        'language' => $repo['language'] ?? 'Unknown',
                    ];
                })
                ->toArray();

        } catch (\Exception $e) {
            error_log('Component discovery error: ' . $e->getMessage());
            return [];
        }
    }

    public function search(string $query): array
    {
        $discovered = $this->discover();
        
        return array_filter($discovered, function ($component) use ($query) {
            return str_contains(strtolower($component['name']), strtolower($query)) ||
                   str_contains(strtolower($component['description']), strtolower($query));
        });
    }
}