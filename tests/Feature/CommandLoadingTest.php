<?php

describe('Core Command Loading', function () {
    it('loads core commands successfully', function () {
        $coreCommands = [
            'install',
            'uninstall',
            'discover',
            'list',
        ];

        foreach ($coreCommands as $command) {
            $this->artisan($command, ['--help' => true])
                ->assertExitCode(0);
        }
    });

    it('shows error for unsupported knowledge commands', function () {
        $this->artisan('know')
            ->expectsOutputToContain('built-in "know" commands have been removed')
            ->assertExitCode(1);
    });

    it('discovers core command capabilities', function () {
        $this->artisan('list')
            ->expectsOutputToContain('discover')
            ->assertExitCode(0);
    });
});
