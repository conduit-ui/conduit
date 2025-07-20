<?php

namespace Conduit\Spotify\Contracts;

interface ApiInterface
{
    /**
     * Get current user's playback state.
     */
    public function getCurrentPlayback(): ?array;

    /**
     * Get currently playing track.
     */
    public function getCurrentTrack(): ?array;

    /**
     * Start/resume playback.
     */
    public function play(?string $contextUri = null, ?string $deviceId = null): bool;

    /**
     * Pause playback.
     */
    public function pause(?string $deviceId = null): bool;

    /**
     * Skip to next track.
     */
    public function skipToNext(?string $deviceId = null): bool;

    /**
     * Skip to previous track.
     */
    public function skipToPrevious(?string $deviceId = null): bool;

    /**
     * Set playback volume.
     */
    public function setVolume(int $volume, ?string $deviceId = null): bool;

    /**
     * Toggle shuffle on/off.
     */
    public function setShuffle(bool $shuffle, ?string $deviceId = null): bool;

    /**
     * Get user's playlists.
     */
    public function getUserPlaylists(int $limit = 20, int $offset = 0): array;

    /**
     * Search for tracks, albums, playlists.
     */
    public function search(string $query, array $types = ['track'], int $limit = 20): array;

    /**
     * Get available devices.
     */
    public function getAvailableDevices(): array;

    /**
     * Transfer playback to device.
     */
    public function transferPlayback(string $deviceId, bool $play = false): bool;

    /**
     * Add track to queue.
     */
    public function addToQueue(string $uri, ?string $deviceId = null): bool;

    /**
     * Get tracks from a playlist.
     */
    public function getPlaylistTracks(string $playlistId, int $limit = 50, int $offset = 0): array;

    /**
     * Create a new playlist.
     */
    public function createPlaylist(string $name, string $description = '', bool $public = false): array;

    /**
     * Add tracks to a playlist.
     */
    public function addTracksToPlaylist(string $playlistId, array $trackUris): bool;

    /**
     * Get artist information including genres.
     */
    public function getArtist(string $artistId): array;
}
