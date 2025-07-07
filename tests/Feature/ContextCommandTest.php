<?php

use App\Services\ContextDetectionService;

test('context command displays project information', function () {
    $this->artisan('context')
        ->expectsOutput('Current Directory Context')
        ->assertExitCode(0);
});

test('context command shows json output', function () {
    // Just verify the command runs successfully with --json flag
    $this->artisan('context --json')
        ->assertExitCode(0);
});