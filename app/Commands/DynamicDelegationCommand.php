<?php

namespace App\Commands;

use App\Services\StandaloneComponentDiscovery;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DynamicDelegationCommand extends Command
{
    protected $name = 'dynamic-delegate';
    protected $description = 'Dynamic delegation to standalone components';
    protected $hidden = true;

    private StandaloneComponentDiscovery $discovery;

    public function __construct(StandaloneComponentDiscovery $discovery)
    {
        parent::__construct();
        $this->discovery = $discovery;
    }

    protected function configure()
    {
        $this->setName('dynamic-delegate')
             ->setDescription('Dynamic delegation to standalone components')
             ->setHidden(true);
    }

    /**
     * Handle dynamic command routing
     */
    public function handleDynamicCommand(string $commandName, array $arguments, array $options): int
    {
        // Parse component:command format
        if (!str_contains($commandName, ':')) {
            return 1; // Not a delegatable command
        }

        [$componentName, $subCommand] = explode(':', $commandName, 2);
        
        // Check if component exists
        $component = $this->discovery->getComponent($componentName);
        
        if (!$component) {
            $this->error("Component '{$componentName}' not found");
            $this->showAvailableComponents();
            return 1;
        }

        // Check if command is available (optional - let component handle it)
        // This allows components to have hidden/undocumented commands

        return $this->delegateToComponent($component, $subCommand, $arguments, $options);
    }

    private function delegateToComponent(array $component, string $command, array $arguments, array $options): int
    {
        $binaryPath = $component['binary'];
        
        // Build the delegation command
        $delegationArgs = [$binaryPath, 'delegated', $command];
        
        // Add positional arguments
        foreach ($arguments as $arg) {
            if ($arg !== null && $arg !== '') {
                $delegationArgs[] = $arg;
            }
        }
        
        // Add options
        foreach ($options as $key => $value) {
            if ($value === true) {
                // Boolean flag
                $delegationArgs[] = "--{$key}";
            } elseif ($value !== false && $value !== null && $value !== '') {
                // Value option
                $delegationArgs[] = "--{$key}";
                $delegationArgs[] = $value;
            }
        }

        $this->line("🔗 Delegating to {$component['name']}: {$command}");

        // Set environment variable to indicate delegation from conduit
        $process = new Process($delegationArgs, null, [
            'CONDUIT_CALLER' => '1'
        ]);
        
        $process->setTimeout(60);

        // Run the process and stream output
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        return $process->getExitCode();
    }

    private function showAvailableComponents(): void
    {
        $components = $this->discovery->discover();
        
        if ($components->isEmpty()) {
            $this->line('No components found. Install components to ~/.conduit/components/');
            return;
        }

        $this->newLine();
        $this->info('Available components:');
        
        foreach ($components as $name => $component) {
            $commandCount = count($component['commands']);
            $this->line("  <comment>{$name}</comment> ({$commandCount} commands)");
        }
    }
}