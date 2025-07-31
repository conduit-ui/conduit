<?php

describe('Command Loading', function () {

    it('loads all commands without dependency injection errors', function () {
        // Get all available commands
        $commands = $this->artisan('list')->run();

        expect($commands)->toBe(0); // Should exit successfully
    });

    it('can access install command', function () {
        $this->artisan('install', ['--help' => true])
            ->assertExitCode(0);
    });

    it('can access uninstall command', function () {
        $this->artisan('uninstall', ['--help' => true])
            ->assertExitCode(0);
    });

    it('can access list:components command', function () {
        $this->artisan('list:components')
            ->assertExitCode(0);
    });

    it('can access know command without crashing', function () {
        // The know command should show migration message
        // Exit code can vary depending on whether component is installed
        $this->artisan('know', ['--no-interaction' => true])
            ->expectsOutputToContain('built-in "know" commands have been removed');
    });

    it('know command shows migration message', function () {
        $this->artisan('know', ['--no-interaction' => true])
            ->expectsOutputToContain('built-in "know" commands have been removed')
            ->expectsOutputToContain('improved knowledge system is now available')
            ->assertExitCode(1);
    });

    it('can access summary/list command', function () {
        $this->artisan('list')
            ->assertExitCode(0);
    });

    it('does not crash on any core command help', function () {
        $coreCommands = [
            'install',
            'uninstall',
            'list:components',
            'know',
            'migrate:knowledge',
            'list',
        ];

        foreach ($coreCommands as $command) {
            $this->artisan($command, ['--help' => true])
                ->assertExitCode(0);
        }
    });

    it('shows component status in list command', function () {
        $this->artisan('list')
            ->expectsOutputToContain('Components:')
            ->assertExitCode(0);
    });
});
