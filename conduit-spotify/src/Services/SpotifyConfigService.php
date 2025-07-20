<?php

namespace Conduit\Spotify\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class SpotifyConfigService
{
    private Repository $cache;

    public function __construct()
    {
        // Use configured cache store or fall back to default, avoiding hardcoded 'file'
        $this->cache = Cache::store(config('spotify.cache_store', config('cache.default')));
    }

    /**
     * Get Spotify client ID from cache or config
     */
    public function getClientId(): ?string
    {
        return $this->cache->get('spotify_client_id') ?: config('spotify.client_id');
    }

    /**
     * Get Spotify client secret from cache or config
     */
    public function getClientSecret(): ?string
    {
        return $this->cache->get('spotify_client_secret') ?: config('spotify.client_secret');
    }

    /**
     * Get redirect URI from config
     */
    public function getRedirectUri(): string
    {
        return config('spotify.redirect_uri', 'http://127.0.0.1:9876/callback');
    }

    /**
     * Get default scopes from config
     */
    public function getDefaultScopes(): array
    {
        return config('spotify.scopes', [
            'user-read-playback-state',
            'user-modify-playback-state',
            'user-read-currently-playing',
            'streaming',
            'user-read-email',
            'user-read-private',
            'playlist-read-private',
            'playlist-read-collaborative',
        ]);
    }

    /**
     * Get auto-play volume from config
     */
    public function getAutoPlayVolume(): int
    {
        return config('spotify.auto_play.volume', 70);
    }

    /**
     * Store credentials securely
     */
    public function storeCredentials(string $clientId, string $clientSecret): void
    {
        $this->cache->put('spotify_client_id', $clientId, now()->addYear());
        $this->cache->put('spotify_client_secret', $clientSecret, now()->addYear());
    }

    /**
     * Store tokens securely
     */
    public function storeTokens(array $tokens): void
    {
        foreach ($tokens as $key => $value) {
            if ($value !== null) {
                $this->cache->put("spotify_{$key}", $value, now()->addYear());
            }
        }
    }

    /**
     * Get stored token
     */
    public function getToken(string $tokenType): ?string
    {
        return $this->cache->get("spotify_{$tokenType}");
    }

    /**
     * Store a single token/value
     */
    public function storeToken(string $tokenType, mixed $value): void
    {
        $this->cache->put("spotify_{$tokenType}", $value, now()->addYear());
    }

    /**
     * Clear stored tokens
     */
    public function clearTokens(): void
    {
        $tokenTypes = ['access_token', 'refresh_token', 'token_expires_at'];
        foreach ($tokenTypes as $tokenType) {
            $this->cache->forget("spotify_{$tokenType}");
        }
    }

    /**
     * Get focus playlists configuration
     */
    public function getFocusPlaylists(): array
    {
        $userConfig = $this->cache->get('spotify_focus_playlists', []);
        $defaultConfig = config('spotify.presets', []);

        return array_merge($defaultConfig, $userConfig);
    }

    /**
     * Store focus playlists configuration
     */
    public function storeFocusPlaylists(array $playlists): void
    {
        $this->cache->put('spotify_focus_playlists', $playlists, now()->addYear());
    }

    /**
     * Get focus statistics
     */
    public function getFocusStats(): array
    {
        return $this->cache->get('spotify_focus_stats', []);
    }

    /**
     * Store focus statistics
     */
    public function storeFocusStats(array $stats): void
    {
        $this->cache->put('spotify_focus_stats', $stats, now()->addYear());
    }

    /**
     * Store credentials for headless authentication
     */
    public function storeHeadlessCredentials(string $username, string $password): void
    {
        $this->cache->put('spotify_username', $username, now()->addYear());
        $this->cache->put('spotify_password', $password, now()->addYear());
    }

    /**
     * Clear headless credentials
     */
    public function clearHeadlessCredentials(): void
    {
        $this->cache->forget('spotify_username');
        $this->cache->forget('spotify_password');
    }

    /**
     * Clear all stored data
     */
    public function clearAll(): void
    {
        $keys = [
            'spotify_client_id',
            'spotify_client_secret',
            'spotify_access_token',
            'spotify_refresh_token',
            'spotify_token_expires_at',
            'spotify_username',
            'spotify_password',
            'spotify_focus_playlists',
            'spotify_focus_stats',
        ];

        foreach ($keys as $key) {
            $this->cache->forget($key);
        }
    }
}
