<?php

use App\Services\ProcessResult;

it('creates process result with all properties', function () {
    $result = new ProcessResult(
        successful: true,
        output: 'Success output',
        errorOutput: ''
    );
    
    expect($result->isSuccessful())->toBeTrue();
    expect($result->getOutput())->toBe('Success output');
    expect($result->getErrorOutput())->toBe('');
});

it('identifies successful result', function () {
    $success = new ProcessResult(true, 'output', '');
    expect($success->isSuccessful())->toBeTrue();
    
    $failure = new ProcessResult(false, 'output', 'error');
    expect($failure->isSuccessful())->toBeFalse();
});

it('identifies error state', function () {
    $success = new ProcessResult(true, 'output', '');
    expect($success->hasError())->toBeFalse();
    
    $failure = new ProcessResult(false, 'output', 'error');
    expect($failure->hasError())->toBeTrue();
});