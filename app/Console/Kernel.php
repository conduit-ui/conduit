<?php

namespace App\Console;

use App\Commands\DynamicDelegationCommand;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Kernel as ConsoleKernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel extends ConsoleKernel
{
    private const CORE_COMMANDS = [
        'discover',
        'install',
        'uninstall',
        'list:components',
        'list',
        'help',
    ];

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

        // Hide non-core commands
        $commands = $this->getApplication()->all();
        foreach ($commands as $command) {
            $name = $command->getName();
            // Hide command if not in core commands and not a dynamic delegation command
            if (! in_array($name, self::CORE_COMMANDS) &&
                $name !== 'dynamic-delegate' &&
                ! str_contains($name, ':')) {
                $command->setHidden(true);
            }
        }
    }

    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(new InputOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Show all commands including non-core commands'
        ));

        return $definition;
    }

    /**
     * Handle command not found by trying component delegation
     */
    public function handle($input, $output = null)
    {
        // If --all flag is set, restore all commands
        if ($input->getOption('all')) {
            $commands = $this->getApplication()->all();
            foreach ($commands as $command) {
                $command->setHidden(false);
            }
        }

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
        $delegator = $this->getApplication()->getLaravel()->make(DynamicDelegationCommand::class);

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
