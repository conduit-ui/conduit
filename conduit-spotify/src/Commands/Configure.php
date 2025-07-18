<?php

namespace Conduit\Spotify\Commands;

use Illuminate\Console\Command;

class Configure extends Command
{
    protected $signature = 'spotify:configure 
                           {--reset : Reset existing credentials}';

    protected $description = 'Alias for spotify:setup (backwards compatibility)';

    public function handle(): int
    {
        $this->warn('⚠️  spotify:configure is deprecated. Use spotify:setup instead.');

        // Forward to the main setup command
        return $this->call('spotify:setup', [
            '--reset' => $this->option('reset'),
        ]);
    }
}
