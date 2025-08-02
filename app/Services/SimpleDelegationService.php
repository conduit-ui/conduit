<?php

namespace App\Services;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;
use Psr\Log\LoggerInterface;

/**
 * Simple delegation service using Laravel's Process facade
 * Components remain standalone - no "delegated" command needed
 */
class SimpleDelegationService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Execute a command on a standalone component
     */
    public function execute(array $component, string $command, array $arguments = [], array $options = []): int
    {
        $binaryPath = $component['binary'];

        // Build command array for Process::run
        $processArgs = [$binaryPath, $command];

        // Add arguments
        foreach ($arguments as $arg) {
            if ($arg !== null && $arg !== '') {
                $processArgs[] = $arg;
            }
        }

        // Add options
        foreach ($options as $key => $value) {
            if ($value === true) {
                $processArgs[] = "--{$key}";
            } elseif ($value !== false && $value !== null && $value !== '') {
                $processArgs[] = "--{$key}";
                $processArgs[] = $value;
            }
        }

        $this->logger->info('Executing component command', [
            'component' => $component['name'],
            'command' => $command,
            'process_args' => $processArgs,
        ]);


        try {
            // Execute using Laravel's Process facade
            $result = Process::run($processArgs);

            // Stream output to user
            echo $result->output();

            if ($result->failed()) {
                echo $result->errorOutput();
                $this->logger->warning('Component command failed', [
                    'component' => $component['name'],
                    'command' => $command,
                    'exit_code' => $result->exitCode(),
                ]);
            }

            return $result->exitCode();

        } catch (ProcessFailedException $e) {
            $this->logger->error('Component execution failed', [
                'component' => $component['name'],
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            echo "❌ Failed to execute command: {$e->getMessage()}\n";

            return 1;
        }
    }

    /**
     * Execute a command with raw arguments (pure pass-through)
     */
    public function executeRaw(array $component, string $command, array $rawArgs = []): int
    {
        $binaryPath = $component['binary'];

        // Build command array for Process::run - just pass everything through
        $processArgs = [$binaryPath, $command];

        // Add all raw arguments as-is
        foreach ($rawArgs as $arg) {
            $processArgs[] = $arg;
        }

        $this->logger->info('Executing component command (raw)', [
            'component' => $component['name'],
            'command' => $command,
            'raw_args' => $rawArgs,
            'process_args' => $processArgs,
        ]);

        try {
            // Execute using Laravel's Process facade
            $result = Process::run($processArgs);

            // Stream output to user
            echo $result->output();

            if ($result->failed()) {
                echo $result->errorOutput();
                $this->logger->warning('Component command failed', [
                    'component' => $component['name'],
                    'command' => $command,
                    'exit_code' => $result->exitCode(),
                ]);
            }

            return $result->exitCode();

        } catch (ProcessFailedException $e) {
            $this->logger->error('Component execution failed', [
                'component' => $component['name'],
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            echo "❌ Failed to execute command: {$e->getMessage()}\n";

            return 1;
        }
    }
}
