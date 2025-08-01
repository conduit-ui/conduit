<?php

namespace App\Commands;

use App\Services\StandaloneComponentDiscovery;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FallbackDelegationCommand extends Command
{
    protected $signature = 'fallback';
    protected $description = 'Fallback command for component delegation';
    protected $hidden = true;

    private StandaloneComponentDiscovery $discovery;

    public function __construct(StandaloneComponentDiscovery $discovery)
    {
        parent::__construct();
        $this->discovery = $discovery;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        // Get the raw command name that was attempted
        $commandName = $input->getFirstArgument();
        
        if (!$commandName || !str_contains($commandName, ':')) {
            return parent::run($input, $output);
        }

        // Parse component:command format
        [$componentName, $subCommand] = explode(':', $commandName, 2);
        
        // Check if this component exists
        $component = $this->discovery->getComponent($componentName);
        
        if (!$component) {
            $this->error("Command '{$commandName}' not found and component '{$componentName}' not available");
            return 1;
        }

        // Check if command is available in component
        if (!in_array($subCommand, $component['commands'])) {
            $this->error("Command '{$subCommand}' not available in component '{$componentName}'");
            $this->line("Available commands: " . implode(', ', $component['commands']));
            return 1;
        }

        // Delegate to the component
        return $this->call('component:delegate', [
            'component' => $componentName,
            'command' => $subCommand,
            '--args' => array_slice($input->getArguments(), 1) // Skip the command name
        ]);
    }
}