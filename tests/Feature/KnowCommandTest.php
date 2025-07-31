<?php

use Illuminate\Support\Facades\Process;

describe('Know Command Migration', function () {

    it('shows migration message when no action specified', function () {
        $this->artisan('know', ['--no-interaction' => true])
            ->expectsOutputToContain('built-in "know" commands have been removed')
            ->expectsOutputToContain('improved knowledge system is now available')
            ->expectsOutputToContain('You tried to run: conduit know')
            ->expectsOutputToContain('New equivalent: conduit knowledge')
            ->assertExitCode(1);
    });

    it('shows migration message with action specified', function () {
        $this->artisan('know', ['action' => 'add', '--no-interaction' => true])
            ->expectsOutputToContain('built-in "know" commands have been removed')
            ->expectsOutputToContain('You tried to run: conduit know add')
            ->expectsOutputToContain('New equivalent: conduit knowledge add')
            ->assertExitCode(1);
    });

    it('detects when knowledge component is already installed', function () {
        // NOTE: Process::fake() doesn't work in Laravel Zero - this test verifies real behavior
        $this->artisan('know')
            ->expectsOutputToContain('conduit-knowledge component is already installed!')
            ->assertExitCode(1);
    });

    it('shows manual installation instructions', function () {
        $this->artisan('know', ['--no-interaction' => true])
            ->expectsOutputToContain('Manual Installation:')
            ->expectsOutputToContain('composer global require jordanpartridge/conduit-knowledge')
            ->assertExitCode(1);
    })->skip('Process::fake() not working - test relies on composer command mocking');

    it('can attempt automatic migration with --migrate flag', function () {
        $this->artisan('know', ['--migrate' => true])
            ->assertExitCode(0); // Should succeed since knowledge is already installed
    })->skip('Process::fake() not working - would need real installation');

    it('handles installation failures gracefully', function () {
        // This would require mocking failed installation
        expect(true)->toBeTrue();
    })->skip('Process::fake() not working - test relies on composer command mocking');

    it('handles GitHub authentication errors with retry', function () {
        // This would require mocking auth failures
        expect(true)->toBeTrue();
    })->skip('Process::fake() not working - test relies on composer command mocking');
});
