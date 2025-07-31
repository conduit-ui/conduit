<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

/**
 * Fallback command for old know commands - redirects to migration
 */
class KnowCommand extends Command
{
    protected $signature = 'know 
                            {action? : The action you were trying to perform}
                            {--help : Show help}';

    protected $description = 'Legacy know command - redirects to new knowledge component';

    protected $hidden = true; // Hide from help listings

    public function handle(): int
    {
        $action = $this->argument('action');

        $this->warn('⚠️  The built-in "know" commands have been removed.');
        $this->info('🚀 An improved knowledge system is now available as a component!');
        $this->newLine();

        if ($action) {
            $this->line("You tried to run: <fg=yellow>conduit know {$action}</>");
            $this->line("New equivalent: <fg=green>conduit knowledge {$action}</>");
            $this->newLine();
        }

        $this->line('To get the new knowledge system, run:');
        $this->line('   <fg=white>conduit migrate:knowledge</>');
        $this->newLine();
        $this->line('Or install directly:');
        $this->line('   <fg=white>conduit install knowledge</>');

        return Command::FAILURE;
    }
}
