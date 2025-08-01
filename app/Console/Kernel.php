<?php

namespace App\Console;

use App\Commands\DynamicDelegationCommand;
use App\Services\StandaloneComponentDiscovery;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Kernel as ConsoleKernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/../Commands');

        require base_path('routes/console.php');
    }

    /**
     * Handle command not found by trying component delegation
     */
    public function handle(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::handle($input, $output);
        } catch (CommandNotFoundException $e) {
            // Try component delegation for component:command format
            $commandName = $input->getFirstArgument();
            
            if ($commandName && str_contains($commandName, ':')) {
                return $this->tryComponentDelegation($commandName, $input, $output);
            }
            
            // Re-throw if not a component command
            throw $e;
        }
    }

    private function tryComponentDelegation(string $commandName, InputInterface $input, OutputInterface $output): int
    {
        $discovery = $this->getApplication()->getLaravel()->make(StandaloneComponentDiscovery::class);
        $delegator = new DynamicDelegationCommand($discovery);
        
        // Extract arguments and options
        $arguments = array_slice($input->getArguments(), 1); // Skip command name
        $options = [];
        
        foreach ($input->getOptions() as $name => $value) {
            if ($value !== false && $value !== null && $value !== '') {
                $options[$name] = $value;
            }
        }
        
        return $delegator->handleDynamicCommand($commandName, $arguments, $options);
    }
}