<?php

namespace App\Commands;

use App\Services\StandaloneComponentDiscovery;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class ComponentDelegationCommand extends Command
{
    protected $signature = 'component:delegate {component} {command} {--args=*}';

    protected $description = 'Delegate commands to standalone components';

    public function handle(StandaloneComponentDiscovery $discovery): int
    {
        $componentName = $this->argument('component');
        $commandName = $this->argument('command');
        $args = $this->option('args') ?? [];

        // Check if component exists
        $component = $discovery->getComponent($componentName);

        if (! $component) {
            $this->error("Component '{$componentName}' not found");
            $this->showAvailableComponents($discovery);

            return 1;
        }

        // Check if command is available
        if (! in_array($commandName, $component['commands'])) {
            $this->error("Command '{$commandName}' not available in component '{$componentName}'");
            $this->line('Available commands: '.implode(', ', $component['commands']));

            return 1;
        }

        return $this->delegateToComponent($component, $commandName, $args);
    }

    private function delegateToComponent(array $component, string $commandName, array $args): int
    {
        $binaryPath = $component['binary'];

        // Validate binary path exists and is executable
        if (! file_exists($binaryPath) || ! is_executable($binaryPath)) {
            $this->error("Component binary is not accessible: {$binaryPath}");

            return 1;
        }

        // Build the delegation command
        $delegationArgs = [$binaryPath, 'delegated', $commandName];

        // Add any additional arguments
        foreach ($args as $arg) {
            // Basic argument sanitization
            if (empty(trim($arg))) {
                continue;
            }
            $delegationArgs[] = $arg;
        }

        // Create and run the process
        $process = new Process($delegationArgs);
        $process->setTimeout(60);
        
        // Run the process and stream output
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        return $process->getExitCode();
    }

    private function showAvailableComponents(StandaloneComponentDiscovery $discovery): void
    {
        $components = $discovery->discover();

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
