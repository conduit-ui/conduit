<?php

namespace JordanPartridge\ConduitSpotify\Contracts;

interface SpotifyApiInterface
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
}
