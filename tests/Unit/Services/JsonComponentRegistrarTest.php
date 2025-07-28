<?php

use App\Services\JsonComponentRegistrar;

beforeEach(function () {
    // Use a temporary test file
    $this->testFile = sys_get_temp_dir().'/test-components.json';
    $this->registrar = new JsonComponentRegistrar($this->testFile);
});

afterEach(function () {
    // Clean up test file
    if (file_exists($this->testFile)) {
        unlink($this->testFile);
    }
});

it('can register a component', function () {
    $success = $this->registrar->registerComponent('test-component', [
        'full_name' => 'example/test-component',
        'description' => 'A test component',
        'service_provider' => 'Example\\TestComponent\\ServiceProvider',
        'commands' => ['test:hello'],
    ]);

    expect($success)->toBeTrue();
    expect($this->registrar->isRegistered('test-component'))->toBeTrue();
});

it('can unregister a component', function () {
    // First register a component
    $this->registrar->registerComponent('test-component', [
        'full_name' => 'example/test-component',
    ]);

    expect($this->registrar->isRegistered('test-component'))->toBeTrue();

    // Then unregister it
    $success = $this->registrar->unregisterComponent('test-component');

    expect($success)->toBeTrue();
    expect($this->registrar->isRegistered('test-component'))->toBeFalse();
});

it('can get all registered components', function () {
    $this->registrar->registerComponent('component1', ['full_name' => 'test/component1']);
    $this->registrar->registerComponent('component2', ['full_name' => 'test/component2']);

    $components = $this->registrar->getRegisteredComponents();

    expect($components)->toHaveCount(2);
    expect($components)->toHaveKeys(['component1', 'component2']);
});

it('can set component status', function () {
    $this->registrar->registerComponent('test-component', [
        'full_name' => 'example/test-component',
    ]);

    $success = $this->registrar->setComponentStatus('test-component', 'inactive');

    expect($success)->toBeTrue();

    $components = $this->registrar->getRegisteredComponents();
    expect($components['test-component']['status'])->toBe('inactive');
});

it('handles non-existent component status update gracefully', function () {
    $success = $this->registrar->setComponentStatus('non-existent', 'active');

    expect($success)->toBeFalse();
});

it('creates registry file if it does not exist', function () {
    expect(file_exists($this->testFile))->toBeFalse();

    $this->registrar->registerComponent('test', ['full_name' => 'test/test']);

    expect(file_exists($this->testFile))->toBeTrue();
});

it('returns empty array for non-existent registry', function () {
    expect(file_exists($this->testFile))->toBeFalse();

    $components = $this->registrar->getRegisteredComponents();

    expect($components)->toBe([]);
});

it('stores component data with correct structure', function () {
    $componentData = [
        'full_name' => 'example/test-component',
        'description' => 'A test component for validation',
        'service_provider' => 'Example\\TestComponent\\ServiceProvider',
        'commands' => ['test:hello', 'test:world'],
    ];

    $this->registrar->registerComponent('test-component', $componentData);

    $components = $this->registrar->getRegisteredComponents();
    $stored = $components['test-component'];

    expect($stored['package'])->toBe('example/test-component');
    expect($stored['description'])->toBe('A test component for validation');
    expect($stored['service_provider'])->toBe('Example\\TestComponent\\ServiceProvider');
    expect($stored['commands'])->toBe(['test:hello', 'test:world']);
    expect($stored['status'])->toBe('active');
    expect($stored['installed_at'])->toBeString();
});
