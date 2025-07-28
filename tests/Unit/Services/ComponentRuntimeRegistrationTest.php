<?php

use App\Services\JsonComponentRegistrar;

it('registers components from JSON file at runtime', function () {
    // Create a temporary JSON file with test components
    $tempFile = sys_get_temp_dir().'/test-runtime-components.json';

    $testRegistry = [
        'registry' => [
            'test-component' => [
                'package' => 'example/test-component',
                'service_provider' => 'Tests\\TestServiceProvider',
                'commands' => ['test:hello'],
                'description' => 'Test component',
                'status' => 'active',
                'installed_at' => now()->toISOString(),
            ],
            'inactive-component' => [
                'package' => 'example/inactive-component',
                'service_provider' => 'Tests\\InactiveServiceProvider',
                'commands' => ['inactive:hello'],
                'description' => 'Inactive test component',
                'status' => 'inactive',
                'installed_at' => now()->toISOString(),
            ],
        ],
        'settings' => [
            'auto_discover' => true,
        ],
    ];

    file_put_contents($tempFile, json_encode($testRegistry, JSON_PRETTY_PRINT));

    // Test the JSON registrar can read the file
    $registrar = new JsonComponentRegistrar($tempFile);
    $components = $registrar->getRegisteredComponents();

    expect($components)->toHaveCount(2);
    expect($components['test-component']['status'])->toBe('active');
    expect($components['inactive-component']['status'])->toBe('inactive');

    // Clean up
    unlink($tempFile);
});

it('handles missing JSON file gracefully', function () {
    $nonExistentFile = sys_get_temp_dir().'/non-existent-components.json';

    $registrar = new JsonComponentRegistrar($nonExistentFile);
    $components = $registrar->getRegisteredComponents();

    expect($components)->toBe([]);
});

it('handles malformed JSON gracefully', function () {
    $tempFile = sys_get_temp_dir().'/malformed-components.json';
    file_put_contents($tempFile, '{ invalid json }');

    $registrar = new JsonComponentRegistrar($tempFile);
    $components = $registrar->getRegisteredComponents();

    expect($components)->toBe([]);

    // Clean up
    unlink($tempFile);
});
