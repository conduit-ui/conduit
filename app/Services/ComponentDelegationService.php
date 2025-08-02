<?php

namespace App\Services;

use App\Services\Security\ComponentSecurityValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ComponentDelegationService
{
    private LoggerInterface $logger;
    
    private ComponentSecurityValidator $securityValidator;

    public function __construct(LoggerInterface $logger, ComponentSecurityValidator $securityValidator = null)
    {
        $this->logger = $logger;
        $this->securityValidator = $securityValidator ?? new ComponentSecurityValidator();
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

        try {
            // Build secure command array
            $delegationArgs = $this->securityValidator->buildSafeCommand(
                $binaryPath,
                $command,
                $arguments,
                $options
            );
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Security validation failed for component delegation', [
                'component' => $component['name'],
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            
            // Return error code without exposing security details to user
            if (app()->runningInConsole() && app()->bound('console')) {
                app('console')->error('Invalid command or arguments provided');
            }
            return 1;
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
