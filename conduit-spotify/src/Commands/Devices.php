<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\AuthInterface;
use Conduit\Spotify\Services\DeviceManager;
use Illuminate\Console\Command;

class Devices extends Command
{
    protected $signature = 'spotify:devices 
                            {action? : list|swap|recall|active|select}
                            {device? : Device ID or name to swap to}
                            {--json : Output as JSON}
                            {--type= : Filter by device type}';

    protected $description = 'Manage Spotify playback devices';

    public function handle(AuthInterface $auth, DeviceManager $devices): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: conduit spotify:login');

            return 1;
        }

        $action = $this->argument('action') ?? 'select';

        try {
            return match ($action) {
                'list' => $this->listDevices($devices),
                'swap' => $this->swapDevice($devices),
                'recall' => $this->recallDevice($devices),
                'active' => $this->showActive($devices),
                'select' => $this->selectDevice($devices),
                default => $this->invalidAction($action),
            };
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function listDevices(DeviceManager $devices): int
    {
        $deviceList = $devices->list();

        if (empty($deviceList)) {
            $this->warn('⚠️  No devices found');
            $this->info('💡 Make sure Spotify is open on at least one device');

            return 0;
        }

        if ($this->option('json')) {
            $active = $devices->getActive();
            $this->line(json_encode([
                'devices' => $deviceList,
                'active_device_id' => $active['id'] ?? null,
            ], JSON_PRETTY_PRINT));

            return 0;
        }

        // Filter by type if specified
        if ($type = $this->option('type')) {
            $deviceList = $devices->findByType($type);
        }

        $this->line('🎵 Available Devices:');
        $this->newLine();

        foreach ($deviceList as $device) {
            $icon = $device['is_active'] ? '▶️ ' : '⏸️ ';
            $volume = $device['volume_percent'] !== null ? "Volume: {$device['volume_percent']}%" : '';

            $this->line("  {$icon} <info>{$device['name']}</info> ({$device['type']})");
            $this->line("      ID: {$device['id']} {$volume}");
            $this->newLine();
        }

        $this->info('💡 Swap devices: conduit spotify:devices swap <device-name>');

        return 0;
    }

    private function swapDevice(DeviceManager $devices): int
    {
        $deviceInput = $this->argument('device');

        if (! $deviceInput) {
            $this->error('❌ Please specify a device ID or name');
            $this->info('💡 Run: conduit spotify:devices list');

            return 1;
        }

        // Try to find device by name first, then by ID
        $targetDevice = $devices->findByName($deviceInput);

        if (! $targetDevice) {
            // Try exact ID match
            $allDevices = $devices->list();
            $targetDevice = collect($allDevices)->firstWhere('id', $deviceInput);
        }

        if (! $targetDevice) {
            $this->error("❌ Device not found: {$deviceInput}");
            $this->info('💡 Run: conduit spotify:devices list');

            return 1;
        }

        $this->info("🔄 Swapping to: {$targetDevice['name']}");

        if ($devices->swap($targetDevice['id'])) {
            $this->info("✅ Playback transferred to {$targetDevice['name']}");

            // Show recall hint
            $previous = $devices->recall();
            if ($previous) {
                $this->line('💡 Swap back: conduit spotify:devices recall');
            }

            return 0;
        }

        $this->error('❌ Failed to transfer playback');

        return 1;
    }

    private function recallDevice(DeviceManager $devices): int
    {
        $previous = $devices->recall();

        if (! $previous) {
            $this->warn('⚠️  No previous device to recall');

            return 0;
        }

        $this->info("🔄 Recalling: {$previous['name']}");

        if ($devices->swap($previous['id'])) {
            $this->info("✅ Playback transferred back to {$previous['name']}");

            return 0;
        }

        $this->error('❌ Failed to recall previous device');

        return 1;
    }

    private function showActive(DeviceManager $devices): int
    {
        $active = $devices->getActive();

        if (! $active) {
            $this->warn('⚠️  No active device');

            return 0;
        }

        if ($this->option('json')) {
            $this->line(json_encode($active, JSON_PRETTY_PRINT));

            return 0;
        }

        $volume = $active['volume_percent'] !== null ? "Volume: {$active['volume_percent']}%" : '';

        $this->line('🎵 Active Device:');
        $this->newLine();
        $this->line("  ▶️  <info>{$active['name']}</info> ({$active['type']})");
        $this->line("      ID: {$active['id']} {$volume}");

        return 0;
    }

    private function selectDevice(DeviceManager $devices): int
    {
        $deviceList = $devices->list();

        if (empty($deviceList)) {
            $this->warn('⚠️  No devices found');
            $this->info('💡 Make sure Spotify is open on at least one device');

            return 0;
        }

        // Get current active device
        $active = $devices->getActive();
        $activeId = $active['id'] ?? null;

        // Build choices for the prompt
        $choices = [];
        foreach ($deviceList as $device) {
            $icon = $device['id'] === $activeId ? '▶️ ' : '   ';
            $volume = $device['volume_percent'] !== null ? " [{$device['volume_percent']}%]" : '';
            $choices[$device['id']] = "{$icon}{$device['name']} ({$device['type']}){$volume}";
        }

        // Add a cancel option
        $choices['_cancel'] = '❌ Cancel';

        $selected = $this->choice(
            '🎵 Select a device to switch to:',
            $choices,
            $activeId // Default to current device
        );

        if ($selected === '_cancel') {
            $this->info('👍 Cancelled');

            return 0;
        }

        // Find the selected device
        $targetDevice = collect($deviceList)->firstWhere('id', $selected);

        if ($targetDevice['id'] === $activeId) {
            $this->info("✅ Already playing on {$targetDevice['name']}");

            return 0;
        }

        $this->info("🔄 Switching to: {$targetDevice['name']}");

        if ($devices->swap($targetDevice['id'])) {
            $this->info("✅ Playback transferred to {$targetDevice['name']}");

            return 0;
        }

        $this->error('❌ Failed to transfer playback');

        return 1;
    }

    private function invalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->info('💡 Valid actions: list, swap, recall, active, select');

        return 1;
    }
}
