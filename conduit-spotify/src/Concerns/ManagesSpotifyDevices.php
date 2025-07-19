<?php

namespace Conduit\Spotify\Concerns;

use Conduit\Spotify\Contracts\ApiInterface;

trait ManagesSpotifyDevices
{
    /**
     * Ensure there's an active Spotify device available for playback.
     */
    protected function ensureActiveDevice(ApiInterface $api): void
    {
        try {
            // First check if we have a currently active device
            $currentPlayback = $api->getCurrentPlayback();
            if ($currentPlayback && isset($currentPlayback['device']) && $currentPlayback['device']['is_active']) {
                $device = $currentPlayback['device'];
                $this->line("🎵 Using active device: {$device['name']} ({$device['type']})");

                return;
            }

            // No active device, try to find and activate one
            $devices = $api->getAvailableDevices();

            if (empty($devices)) {
                $this->warn('⚠️  No Spotify devices found');
                $this->line('💡 Make sure Spotify is open on a device:');
                $this->line('  • Open Spotify on your phone, computer, or web player');
                $this->line('  • Wait a moment for devices to register');
                $this->line('  • Then try this command again');

                return;
            }

            // Check if any device is already active
            $activeDevice = collect($devices)->firstWhere('is_active', true);
            if ($activeDevice) {
                $this->line("🎵 Using active device: {$activeDevice['name']} ({$activeDevice['type']})");

                return;
            }

            // Smart device selection priority
            $preferredDevice = $this->selectBestDevice($devices);

            if ($preferredDevice) {
                $this->line("🔄 Activating device: {$preferredDevice['name']} ({$preferredDevice['type']})");

                $success = $this->attemptDeviceActivation($api, $preferredDevice);

                if ($success) {
                    $this->line('✅ Device activated successfully');
                } else {
                    // Try fallback devices
                    $this->tryFallbackDevices($api, $devices, $preferredDevice['id']);
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Device activation failed: {$e->getMessage()}");
            $this->line('💡 Try opening Spotify on a device and running the command again');
        }
    }

    /**
     * Select the best available device based on priority
     */
    protected function selectBestDevice(array $devices): ?array
    {
        $devicePriority = [
            'computer' => 1,
            'smartphone' => 2,
            'tablet' => 3,
            'speaker' => 4,
            'tv' => 5,
            'castingDevice' => 6,
            'unknown' => 7,
        ];

        $sortedDevices = collect($devices)->sortBy(function ($device) use ($devicePriority) {
            $type = strtolower($device['type'] ?? 'unknown');

            return $devicePriority[$type] ?? 10;
        });

        return $sortedDevices->first();
    }

    /**
     * Attempt to activate a device with retry logic
     */
    protected function attemptDeviceActivation(ApiInterface $api, array $device): bool
    {
        $maxRetries = 3;
        $retryDelay = 1; // seconds

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $api->transferPlayback($device['id'], true);

                // Wait a moment and verify activation
                sleep($retryDelay);

                $currentPlayback = $api->getCurrentPlayback();
                if ($currentPlayback &&
                    isset($currentPlayback['device']) &&
                    $currentPlayback['device']['id'] === $device['id'] &&
                    $currentPlayback['device']['is_active']) {
                    return true;
                }

            } catch (\Exception $e) {
                if ($i === $maxRetries - 1) {
                    $this->warn("⚠️  Failed to activate {$device['name']}: {$e->getMessage()}");
                }
            }

            if ($i < $maxRetries - 1) {
                sleep($retryDelay);
            }
        }

        return false;
    }

    /**
     * Try fallback devices if primary selection fails
     */
    protected function tryFallbackDevices(ApiInterface $api, array $devices, string $excludeId): void
    {
        $fallbackDevices = collect($devices)
            ->filter(fn ($device) => $device['id'] !== $excludeId)
            ->take(2); // Try up to 2 fallback devices

        foreach ($fallbackDevices as $device) {
            $this->line("🔄 Trying fallback device: {$device['name']}");

            if ($this->attemptDeviceActivation($api, $device)) {
                $this->line('✅ Fallback device activated successfully');

                return;
            }
        }

        $this->warn('⚠️  Could not activate any available devices');
        $this->line('💡 Try manually selecting a device in Spotify and run the command again');
    }
}
