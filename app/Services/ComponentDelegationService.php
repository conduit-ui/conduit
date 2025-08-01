<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class ComponentDelegationService
{
    /**
     * Delegate a command to a standalone component
     */
    public function delegate(array $component, string $command, array $arguments = [], array $options = []): int
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

        // Set environment variable to indicate delegation from conduit
        $process = new Process($delegationArgs, null, [
            'CONDUIT_CALLER' => '1',
        ]);

        $process->setTimeout(60);

        // Run the process and stream output
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        return $process->getExitCode();
    }
}
