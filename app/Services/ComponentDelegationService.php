<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ComponentDelegationService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Delegate a command to a standalone component
     */
    public function delegate(array $component, string $command, array $arguments = [], array $options = []): int
    {
        $binaryPath = $component['binary'];

        $this->logger->info('Delegating command', [
            'component' => $component['name'],
            'command' => $command,
            'arguments' => $arguments,
            'options' => $options,
        ]);

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

        // Set environment variable to indicate delegation from conduit
        $process = new Process($delegationArgs, null, [
            'CONDUIT_CALLER' => '1',
        ]);

        $process->setTimeout(60);

        // Run the process and stream output
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        try {
            $exitCode = $process->getExitCode();

            if ($exitCode !== 0) {
                $this->logger->warning('Component delegation failed', [
                    'component' => $component['name'],
                    'command' => $command,
                    'exit_code' => $exitCode,
                ]);
            }

            return $exitCode;
        } catch (ProcessFailedException $e) {
            $this->logger->error('Component delegation process failed', [
                'component' => $component['name'],
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }
}
