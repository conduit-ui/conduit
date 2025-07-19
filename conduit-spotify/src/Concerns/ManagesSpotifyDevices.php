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
        // Device priority: Computer > Smartphone > Speaker > Other
        $priorities = [
            'Computer' => 4,
            'Smartphone' => 3,
            'Speaker' => 2,
            'TV' => 1,
            'Unknown' => 0
        ];

        $scoredDevices = [];
        foreach ($devices as $device) {
            $type = $device['type'] ?? 'Unknown';
            $score = $priorities[$type] ?? 0;
            
            // Boost score for devices that support volume control
            if ($device['supports_volume'] ?? false) {
                $score += 2;
            }
            
            // Boost score for recently active devices
            if ($device['is_active'] ?? false) {
                $score += 5;
            }
            
            $scoredDevices[] = [
                'device' => $device,
                'score' => $score
            ];
        }

        // Sort by score (highest first)
        usort($scoredDevices, fn($a, $b) => $b['score'] <=> $a['score']);

        return !empty($scoredDevices) ? $scoredDevices[0]['device'] : null;
    }

    /**
     * Attempt to activate a device with retry logic
     */
    protected function attemptDeviceActivation(ApiInterface $api, array $device): bool
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            
            try {
                if ($api->transferPlayback($device['id'], false)) {
                    // Wait and verify activation
                    sleep(2);
                    
                    $currentPlayback = $api->getCurrentPlayback();
                    if ($currentPlayback && 
                        isset($currentPlayback['device']) && 
                        $currentPlayback['device']['id'] === $device['id']) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                $this->warn("⚠️  Activation attempt {$attempt} failed: {$e->getMessage()}");
            }

            if ($attempt < $maxAttempts) {
                $this->line("⏳ Retrying in 2 seconds... (attempt {$attempt}/{$maxAttempts})");
                sleep(2);
            }
        }

        return false;
    }

    /**
     * Try fallback devices if primary selection fails
     */
    protected function tryFallbackDevices(ApiInterface $api, array $devices, string $excludeId): void
    {
        $fallbackDevices = array_filter($devices, fn($d) => $d['id'] !== $excludeId);
        
        foreach ($fallbackDevices as $device) {
            $this->line("🔄 Trying fallback device: {$device['name']} ({$device['type']})");
            
            if ($this->attemptDeviceActivation($api, $device)) {
                $this->line('✅ Fallback device activated successfully');
                return;
            }
        }

        $this->warn('⚠️  Could not activate any devices');
        $this->line('💡 Available devices:');
        foreach ($devices as $device) {
            $status = ($device['is_active'] ?? false) ? '(active)' : '(inactive)';
            $this->line("  • {$device['name']} ({$device['type']}) {$status}");
        }
    }
}