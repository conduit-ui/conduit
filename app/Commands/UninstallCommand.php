<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\confirm;

/**
 * Simple component uninstallation command using composer global remove
 * 
 * Replaces the complex ComponentsCommand uninstall functionality with
 * direct composer global operations for cleaner architecture.
 */
class UninstallCommand extends Command
{
    protected $signature = 'uninstall 
                            {component : Component name to uninstall (e.g. knowledge, spotify)}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Uninstall a Conduit component using composer global remove';

    public function handle(): int
    {
        $componentName = $this->argument('component');
        $force = $this->option('force');

        // Map component name to package name
        $packageName = $this->resolvePackageName($componentName);

        $this->info("Uninstalling component: {$componentName}");
        $this->line("Package: {$packageName}");

        // Check if component is installed
        if (!$this->isGloballyInstalled($packageName)) {
            $this->warn("Component '{$componentName}' is not installed globally.");
            $this->line("Run 'composer global show' to see installed packages.");
            return Command::SUCCESS; // Not an error, just not installed
        }

        // Confirm removal unless forced
        if (!$force) {
            $confirmed = confirm("Remove component '{$componentName}' ({$packageName})?", false);
            if (!$confirmed) {
                $this->info("Uninstallation cancelled.");
                return Command::SUCCESS;
            }
        }

        // Remove the package
        $this->info("Running: composer global remove {$packageName}");
        
        $process = new Process(['composer', 'global', 'remove', $packageName]);
        $process->setTimeout(300); // 5 minutes timeout
        
        $exitCode = $process->run(function ($type, $buffer) {
            // Stream output in real-time
            $this->getOutput()->write($buffer);
        });

        if ($exitCode === 0) {
            $this->info("✅ Successfully uninstalled '{$componentName}' component!");
            
            // Show cleanup hint
            $this->newLine();
            $this->line("💡 Component commands are no longer available.");
            $this->line("   Run 'conduit list' to see remaining commands.");
            
            return Command::SUCCESS;
        } else {
            $this->error("❌ Failed to uninstall component '{$componentName}'.");
            $this->line("Error output:");
            $this->line($process->getErrorOutput());
            
            return Command::FAILURE;
        }
    }

    /**
     * Resolve component name to full package name
     */
    private function resolvePackageName(string $componentName): string
    {
        // Known component mappings (same as InstallCommand)
        $knownComponents = [
            'knowledge' => 'jordanpartridge/conduit-knowledge',
            'know' => 'jordanpartridge/conduit-know', // Support removal of legacy know
            'spotify' => 'jordanpartridge/conduit-spotify',
            'env-manager' => 'jordanpartridge/conduit-env-manager',
            'github' => 'jordanpartridge/conduit-github',
        ];

        // Return known mapping or assume jordanpartridge/conduit-{name} pattern
        return $knownComponents[$componentName] ?? "jordanpartridge/conduit-{$componentName}";
    }

    /**
     * Check if a package is installed globally
     */
    private function isGloballyInstalled(string $packageName): bool
    {
        $process = new Process(['composer', 'global', 'show', $packageName]);
        $process->run();
        
        return $process->getExitCode() === 0;
    }
}