<?php

namespace App\Commands;

use App\Services\Traits\DiscoverComponents;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class Discover extends Command
{
    use DiscoverComponents;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discover {--search=* : Filter by search terms}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover Conduit components via GitHub topics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Discovering Conduit components...');

        $searchTerms = $this->option('search');

        if (! empty($searchTerms)) {
            $components = [];
            foreach ($searchTerms as $term) {
                $components = array_merge($components, $this->search($term));
            }
            // Remove duplicates
            $components = array_unique($components, SORT_REGULAR);
        } else {
            $components = $this->discover();
        }

        if (empty($components)) {
            $this->warn('No components found matching your criteria.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d component(s):', count($components)));
        $this->newLine();

        foreach ($components as $component) {
            $this->line("📦 <comment>{$component['name']}</comment>");
            $this->line("   {$component['description']}");
            $this->line("   <info>Repository:</info> {$component['full_name']}");
            $this->line("   <info>Language:</info> {$component['language']} | <info>Stars:</info> {$component['stars']} | <info>Updated:</info> {$component['updated_at']}");

            if (! empty($component['topics'])) {
                $this->line('   <info>Topics:</info> '.implode(', ', $component['topics']));
            }

            $this->line("   <info>Install:</info> conduit install {$component['full_name']}");
            $this->newLine();
        }

        $this->info('💡 Use "conduit install <repo>" to install a component');

        return self::SUCCESS;
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // Optional: Schedule component discovery for updates
        // $schedule->command(static::class)->daily();
    }
}
