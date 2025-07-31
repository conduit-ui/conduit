<?php

namespace App\Services\Traits;

use Illuminate\Support\Facades\Process;

/**
 * Trait for component listing capabilities
 */
trait ListsComponents
{
    public function listInstalled(): array
    {
        $process = Process::run(['composer', 'global', 'show', '--format=json']);

        if (! $process->successful()) {
            return [];
        }

        $output = $process->output();
        $data = json_decode($output, true);

        if (! isset($data['installed'])) {
            return [];
        }

        // Filter for Conduit components
        $packages = array_filter($data['installed'], function ($package) {
            return str_starts_with($package['name'], 'jordanpartridge/conduit-');
        });

        return array_map(function ($package) {
            return [
                'name' => $this->extractComponentName($package['name']),
                'package' => $package['name'],
                'version' => $package['version'] ?? 'N/A',
                'description' => $package['description'] ?? 'N/A',
            ];
        }, $packages);
    }

    public function listWithDetails(): array
    {
        return $this->listInstalled(); // Same implementation for now
    }

    /**
     * Extract component name from package name
     */
    private function extractComponentName(string $packageName): string
    {
        if (str_starts_with($packageName, 'jordanpartridge/conduit-')) {
            return substr($packageName, 24); // Remove 'jordanpartridge/conduit-' prefix
        }

        return $packageName;
    }
}
