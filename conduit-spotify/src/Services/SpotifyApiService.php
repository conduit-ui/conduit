<?php

namespace JordanPartridge\ConduitSpotify\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;

class SpotifyApiService implements SpotifyApiInterface
{
    private Client $httpClient;
    private SpotifyAuthInterface $auth;

    public function __construct(SpotifyAuthInterface $auth)
    {
        $this->auth = $auth;
        $this->httpClient = new Client([
            'base_uri' => 'https://api.spotify.com/v1/',
            'timeout' => 30,
        ]);
    }

    public function getCurrentPlayback(): ?array
    {
        return $this->makeRequest('GET', 'me/player');
    }

    public function getCurrentTrack(): ?array
    {
        return $this->makeRequest('GET', 'me/player/currently-playing');
    }

    public function play(?string $contextUri = null, ?string $deviceId = null): bool
    {
        $data = [];
        if ($contextUri) {
            $data['context_uri'] = $contextUri;
        }

        $url = 'me/player/play';
        if ($deviceId) {
            $url .= "?device_id={$deviceId}";
        }

        $result = $this->makeRequest('PUT', $url, $data);
        return $result !== false;
    }

    public function pause(?string $deviceId = null): bool
    {
        $url = 'me/player/pause';
        if ($deviceId) {
            $url .= "?device_id={$deviceId}";
        }

        $result = $this->makeRequest('PUT', $url);
        return $result !== false;
    }

    public function skipToNext(?string $deviceId = null): bool
    {
        $url = 'me/player/next';
        if ($deviceId) {
            $url .= "?device_id={$deviceId}";
        }

        $result = $this->makeRequest('POST', $url);
        return $result !== false;
    }

    public function skipToPrevious(?string $deviceId = null): bool
    {
        $url = 'me/player/previous';
        if ($deviceId) {
            $url .= "?device_id={$deviceId}";
        }

        $result = $this->makeRequest('POST', $url);
        return $result !== false;
    }

    public function setVolume(int $volume, ?string $deviceId = null): bool
    {
        $volume = max(0, min(100, $volume)); // Clamp between 0-100
        
        $url = "me/player/volume?volume_percent={$volume}";
        if ($deviceId) {
            $url .= "&device_id={$deviceId}";
        }

        $result = $this->makeRequest('PUT', $url);
        return $result !== false;
    }

    public function setShuffle(bool $shuffle, ?string $deviceId = null): bool
    {
        $shuffleState = $shuffle ? 'true' : 'false';
        
        $url = "me/player/shuffle?state={$shuffleState}";
        if ($deviceId) {
            $url .= "&device_id={$deviceId}";
        }

        $result = $this->makeRequest('PUT', $url);
        return $result !== false;
    }

    public function getUserPlaylists(int $limit = 20, int $offset = 0): array
    {
        $result = $this->makeRequest('GET', "me/playlists?limit={$limit}&offset={$offset}");
        return $result['items'] ?? [];
    }

    public function search(string $query, array $types = ['track'], int $limit = 20): array
    {
        $typeString = implode(',', $types);
        $encodedQuery = urlencode($query);
        
        return $this->makeRequest('GET', "search?q={$encodedQuery}&type={$typeString}&limit={$limit}") ?? [];
    }

    public function getAvailableDevices(): array
    {
        $result = $this->makeRequest('GET', 'me/player/devices');
        return $result['devices'] ?? [];
    }

    public function transferPlayback(string $deviceId, bool $play = false): bool
    {
        $data = [
            'device_ids' => [$deviceId],
            'play' => $play,
        ];

        $result = $this->makeRequest('PUT', 'me/player', $data);
        return $result !== false;
    }

    /**
     * Make an authenticated request to Spotify API.
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array|false
    {
        $accessToken = $this->auth->getAccessToken();
        
        if (!$accessToken) {
            throw new \Exception('Spotify authentication required. Run: conduit spotify auth');
        }

        try {
            $options = [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
            ];

            if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $options['json'] = $data;
            }

            $response = $this->httpClient->request($method, $endpoint, $options);

            // Some Spotify endpoints return 204 No Content on success
            if ($response->getStatusCode() === 204) {
                return [];
            }

            $body = $response->getBody()->getContents();
            return json_decode($body, true) ?? [];

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                
                // Handle token expiration
                if ($statusCode === 401) {
                    throw new \Exception('Spotify token expired. Please re-authenticate: conduit spotify auth');
                }
                
                // Handle rate limiting
                if ($statusCode === 429) {
                    $retryAfter = $e->getResponse()->getHeader('Retry-After')[0] ?? 1;
                    throw new \Exception("Spotify API rate limit exceeded. Please try again in {$retryAfter} seconds.");
                }

                // Handle no active device
                if ($statusCode === 404 && str_contains($endpoint, 'player')) {
                    throw new \Exception('No active Spotify device found. Please open Spotify on a device and try again.');
                }
            }

            throw new \Exception('Spotify API error: ' . $e->getMessage());
        }
    }
}