<?php

use App\Services\ProcessResult;

it('creates process result with all properties', function () {
    $result = new ProcessResult(
        exitCode: 0,
        output: 'Success output',
        errorOutput: '',
        command: 'test command'
    );
    
    expect($result->getExitCode())->toBe(0);
    expect($result->getOutput())->toBe('Success output');
    expect($result->getErrorOutput())->toBe('');
    expect($result->getCommand())->toBe('test command');
});

it('identifies successful result', function () {
    $success = new ProcessResult(0, 'output', '', 'command');
    expect($success->isSuccessful())->toBeTrue();
    
    $failure = new ProcessResult(1, 'output', 'error', 'command');
    expect($failure->isSuccessful())->toBeFalse();
});

it('identifies failed result', function () {
    $success = new ProcessResult(0, 'output', '', 'command');
    expect($success->failed())->toBeFalse();
    
    $failure = new ProcessResult(1, 'output', 'error', 'command');
    expect($failure->failed())->toBeTrue();
});