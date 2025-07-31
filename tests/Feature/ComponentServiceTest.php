<?php

use App\Services\ComponentService;
use Illuminate\Support\Facades\Process;

describe('ComponentService', function () {

    beforeEach(function () {
        $this->componentService = app(ComponentService::class);
    });

    it('can be instantiated from the container', function () {
        expect($this->componentService)->toBeInstanceOf(ComponentService::class);
    });

    it('can list installed components', function () {
        $installed = $this->componentService->listInstalled();

        expect($installed)->toBeArray();

        // Each component should have required fields
        foreach ($installed as $component) {
            expect($component)->toHaveKeys(['name', 'package', 'version']);
        }
    });

    it('can check if a component is installed', function () {
        // Test with a component we know exists
        $installed = $this->componentService->listInstalled();

        if (! empty($installed)) {
            $firstComponent = array_values($installed)[0]; // Get first value regardless of key
            $isInstalled = $this->componentService->isInstalled($firstComponent['name']);
            expect($isInstalled)->toBeTrue();
        }

        // Test with a component that definitely doesn't exist
        $isInstalled = $this->componentService->isInstalled('non-existent-component-12345');
        expect($isInstalled)->toBeFalse();
    });

    it('can get component info', function () {
        $installed = $this->componentService->listInstalled();

        if (! empty($installed)) {
            $firstComponent = array_values($installed)[0]; // Get first value regardless of key
            $info = $this->componentService->getComponentInfo($firstComponent['name']);

            expect($info)->not->toBeNull();
            expect($info)->toHaveKeys(['name', 'package', 'version']);
        }
    });

    it('returns null for non-existent component info', function () {
        $info = $this->componentService->getComponentInfo('non-existent-component-12345');
        expect($info)->toBeNull();
    });

    it('handles install operations', function () {
        // NOTE: Process::fake() doesn't work properly in Laravel Zero testing environment
        // This test verifies the ComponentResult structure instead of full installation

        $result = $this->componentService->install('non-existent-test-component-12345');

        // The result should be a ComponentResult even if the install fails
        expect($result)->toBeInstanceOf(\App\Services\ComponentResult::class);
        expect($result->isSuccessful())->toBeFalse(); // Should fail for non-existent component
        expect($result->getMessage())->toContain('Failed to install component');
    })->skip('Process::fake() not working in Laravel Zero - see ComponentServiceDebugTest');

    it('handles install failures gracefully', function () {
        // Test with a component that definitely doesn't exist
        $result = $this->componentService->install('definitely-non-existent-component-xyz-123');

        expect($result)->toBeInstanceOf(\App\Services\ComponentResult::class);
        expect($result->isSuccessful())->toBeFalse();
        expect($result->getMessage())->toContain('Failed to install component');
    });
});
