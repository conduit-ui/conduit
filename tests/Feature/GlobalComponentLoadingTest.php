<?php

use App\Services\GlobalComponentDiscovery;
use Illuminate\Support\Facades\Process;

describe('Global Component Loading', function () {

    it('can discover globally installed components', function () {
        $discovery = app(GlobalComponentDiscovery::class);
        $components = $discovery->discover();

        expect($components)->toBeInstanceOf(\Illuminate\Support\Collection::class);

        // Should find at least the knowledge component if it's installed
        if ($components->isNotEmpty()) {
            $componentNames = $components->pluck('name');
            expect($componentNames)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        }
    });

    it('loads component service providers correctly', function () {
        $discovery = app(GlobalComponentDiscovery::class);
        $components = $discovery->discover();

        foreach ($components as $component) {
            expect($component)->toHaveKeys(['name', 'package', 'path', 'providers']);

            // If component has providers, they should be valid class names
            if (! empty($component['providers'])) {
                foreach ($component['providers'] as $provider) {
                    expect($provider)->toBeString();
                    expect($provider)->toMatch('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/');
                }
            }
        }
    });

    it('can load component without errors', function () {
        $discovery = app(GlobalComponentDiscovery::class);
        $components = $discovery->discover();

        foreach ($components as $component) {
            // This should not throw an exception
            expect(fn () => $discovery->loadComponent($component))->not->toThrow(Exception::class);
        }
    });

    it('has knowledge component available globally', function () {
        // Check if knowledge component is installed globally
        $process = Process::run(['composer', 'global', 'show', 'jordanpartridge/conduit-knowledge']);

        if ($process->successful()) {
            // If it's installed, our discovery should find it
            $discovery = app(GlobalComponentDiscovery::class);
            $components = $discovery->discover();

            $knowledgeComponent = $components->firstWhere('name', 'Knowledge');
            expect($knowledgeComponent)->not->toBeNull();
            expect($knowledgeComponent['package'])->toBe('jordanpartridge/conduit-knowledge');
        } else {
            // If not installed, skip this test
            $this->markTestSkipped('conduit-knowledge component not installed globally');
        }
    });
});
