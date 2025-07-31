<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\confirm;

/**
 * Simple component installation command using composer global require
 * 
 * Replaces the complex ComponentsCommand install functionality with
 * direct composer global operations for cleaner architecture.
 */
class InstallCommand extends Command
{
    protected $signature = 'install 
                            {component : Component name (e.g. knowledge, spotify)} 
                            {--force : Force reinstallation if already installed}
                            {--dev : Install development version}';

    protected $description = 'Install a Conduit component using composer global require';

    public function handle(): int
    {
        $componentName = $this->argument('component');
        $force = $this->option('force');
        $dev = $this->option('dev');

        // Handle legacy 'know' component migration
        if ($componentName === 'know') {
            $this->warn('The "know" component has been renamed to "knowledge".');
            $this->info('This migration will:');
            $this->line('  • Install jordanpartridge/conduit-knowledge globally');
            $this->line('  • Remove jordanpartridge/conduit-know if installed');
            
            if (confirm('Continue with automatic migration to "knowledge"?', true)) {
                // First try to remove old 'know' component
                $this->migrateFromKnowToKnowledge();
                $componentName = 'knowledge';
            } else {
                $this->info('Installation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Map component name to package name
        $packageName = $this->resolvePackageName($componentName);
        
        $this->info("Installing component: {$componentName}");
        $this->line("Package: {$packageName}");

        // Check if already installed (unless forced)
        if (!$force && $this->isGloballyInstalled($packageName)) {
            $this->error("Component '{$componentName}' is already installed globally.");
            $this->line("Use --force to reinstall or 'conduit uninstall {$componentName}' first.");
            return Command::FAILURE;
        }

        // Build composer command
        $composerArgs = ['global', 'require', $packageName];
        
        if ($dev) {
            $composerArgs[] = '--dev';
        }

        if ($force) {
            // Remove first, then install
            $this->info("Removing existing installation...");
            $removeProcess = new Process(['composer', 'global', 'remove', $packageName]);
            $removeProcess->run();
            // Continue even if removal fails
        }

        // Install the package
        $this->info("Running: composer " . implode(' ', $composerArgs));
        
        $process = new Process(['composer'] + $composerArgs);
        $process->setTimeout(300); // 5 minutes timeout
        
        $exitCode = $process->run(function ($type, $buffer) {
            // Stream output in real-time
            $this->getOutput()->write($buffer);
        });

        if ($exitCode === 0) {
            $this->info("✅ Successfully installed '{$componentName}' component!");
            
            // Show available commands hint
            $this->newLine();
            $this->line("💡 Component commands should now be available.");
            $this->line("   Run 'conduit list' to see all available commands.");
            
            return Command::SUCCESS;
        } else {
            $this->error("❌ Failed to install component '{$componentName}'.");
            $this->line("Error output:");
            $this->line($process->getErrorOutput());
            
            return Command::FAILURE;
        }
    }

    /**
     * Migrate from old 'know' component to new 'knowledge' component
     */
    private function migrateFromKnowToKnowledge(): void
    {
        $oldPackage = 'jordanpartridge/conduit-know';
        
        if ($this->isGloballyInstalled($oldPackage)) {
            $this->info("Removing old 'know' component...");
            $process = new Process(['composer', 'global', 'remove', $oldPackage]);
            $process->run();
            
            if ($process->getExitCode() === 0) {
                $this->info("✅ Successfully removed old 'know' component.");
            } else {
                $this->warn("⚠️  Could not remove old 'know' component automatically.");
                $this->line("You may need to run: composer global remove {$oldPackage}");
            }
        }
    }

    /**
     * Resolve component name to full package name
     */
    private function resolvePackageName(string $componentName): string
    {
        // Known component mappings
        $knownComponents = [
            'knowledge' => 'jordanpartridge/conduit-knowledge',
            'spotify' => 'jordanpartridge/conduit-spotify',
            'env-manager' => 'jordanpartridge/conduit-env-manager',
            'github' => 'jordanpartridge/conduit-github',
        ];

        // Return known mapping or assume jordanpartridge/conduit-{name} pattern
        return $knownComponents[$componentName] ?? "jordanpartridge/conduit-{$componentName}";
    }

    /**
     * Check if a package is already installed globally
     */
    private function isGloballyInstalled(string $packageName): bool
    {
        $process = new Process(['composer', 'global', 'show', $packageName]);
        $process->run();
        
        return $process->getExitCode() === 0;
    }
}