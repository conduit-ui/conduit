<?php

test('discover command successfully discovers components', function () {
    $this->artisan('discover')
        ->expectsOutput('🔍 Discovering Conduit components...')
        ->assertExitCode(0);
});

test('discover command supports search functionality', function () {
    $this->artisan('discover --search=nonexistent')
        ->expectsOutput('🔍 Discovering Conduit components...')
        ->assertExitCode(0);
});
