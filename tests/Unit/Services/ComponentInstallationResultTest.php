<?php

use App\Services\ComponentInstallationResult;
use App\Services\ProcessResult;

it('creates success result', function () {
    $componentInfo = ['name' => 'test-component', 'version' => '1.0'];
    $commands = ['test:command', 'another:command'];

    $result = ComponentInstallationResult::success($componentInfo, $commands);

    expect($result->isSuccessful())->toBeTrue();
    expect($result->getMessage())->toBe('Component installed successfully');
    expect($result->getComponentInfo())->toBe($componentInfo);
    expect($result->getCommands())->toBe($commands);
    expect($result->getProcessResult())->toBeNull();
});

it('creates failure result', function () {
    $processResult = new ProcessResult(1, 'output', 'error output', 'failed command');
    $result = ComponentInstallationResult::failed('Installation failed', $processResult);

    expect($result->isSuccessful())->toBeFalse();
    expect($result->getMessage())->toBe('Installation failed');
    expect($result->getComponentInfo())->toBe([]);
    expect($result->getCommands())->toBe([]);
    expect($result->getProcessResult())->toBe($processResult);
});

it('creates failure result without process result', function () {
    $result = ComponentInstallationResult::failed('Installation failed');

    expect($result->isSuccessful())->toBeFalse();
    expect($result->getMessage())->toBe('Installation failed');
    expect($result->getProcessResult())->toBeNull();
});
