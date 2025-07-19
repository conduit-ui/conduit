<?php

namespace Conduit\Spotify\Services;

use Conduit\Spotify\Contracts\ApiInterface;
use Illuminate\Support\Facades\Cache;

class DeviceManager
{
    public function __construct(
        private ApiInterface $api
    ) {}

    public function list(): array
    {
        $devices = $this->api->getAvailableDevices();

        // Cache the device list for quick recall
        Cache::put('spotify.devices.last', $devices, now()->addMinutes(5));

        return $this->formatDevices($devices);
    }

    public function getActive(): ?array
    {
        $playback = $this->api->getCurrentPlayback();

        if ($playback && isset($playback['device'])) {
            return $playback['device'];
        }

        return null;
    }

    public function swap(string $deviceId): bool
    {
        // Get current state before swapping
        $currentPlayback = $this->api->getCurrentPlayback();
        $wasPlaying = $currentPlayback['is_playing'] ?? false;

        // Store previous device for recall
        if ($currentPlayback && isset($currentPlayback['device'])) {
            Cache::put('spotify.devices.previous', $currentPlayback['device'], now()->addHours(1));
        }

        // Transfer playback
        $success = $this->api->transferPlayback($deviceId, $wasPlaying);

        if ($success) {
            Cache::put('spotify.devices.current', $deviceId, now()->addHours(1));
        }

        return $success;
    }

    public function recall(): ?array
    {
        return Cache::get('spotify.devices.previous');
    }

    public function getLastList(): array
    {
        return Cache::get('spotify.devices.last', []);
    }

    public function findByName(string $name): ?array
    {
        $devices = $this->list();

        // Case-insensitive partial match
        $name = strtolower($name);

        foreach ($devices as $device) {
            if (str_contains(strtolower($device['name']), $name)) {
                return $device;
            }
        }

        return null;
    }

    public function findByType(string $type): array
    {
        $devices = $this->list();
        $type = strtolower($type);

        return array_filter($devices, function ($device) use ($type) {
            return strtolower($device['type']) === $type;
        });
    }

    private function formatDevices(array $devices): array
    {
        return array_map(function ($device) {
            return [
                'id' => $device['id'],
                'name' => $device['name'],
                'type' => $device['type'],
                'is_active' => $device['is_active'],
                'volume_percent' => $device['volume_percent'] ?? null,
                'supports_volume' => $device['supports_volume'] ?? false,
            ];
        }, $devices);
    }
}
