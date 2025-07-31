<?php

namespace App\Commands;

use App\Services\ComponentService;
use LaravelZero\Framework\Commands\Command;

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

    public function handle(ComponentService $componentService): int
    {
        $componentName = $this->argument('component');
        $force = $this->option('force');
        $dev = $this->option('dev');

        try {
            // Handle legacy 'know' component migration
            if ($componentName === 'know') {
                $this->warn('The "know" component has been renamed to "knowledge".');
                $this->info('This migration will:');
                $this->line('  • Install jordanpartridge/conduit-knowledge globally');
                $this->line('  • Remove jordanpartridge/conduit-know if installed');

                if (confirm('Continue with automatic migration to "knowledge"?', true)) {
                    $result = $componentService->migrateLegacyComponent('know', 'knowledge');

                    if ($result->isSuccessful()) {
                        $this->info('✅ '.$result->getMessage());
                        $this->newLine();
                        $this->line('💡 Component commands should now be available.');
                        $this->line("   Run 'conduit list' to see all available commands.");

                        return Command::SUCCESS;
                    } else {
                        $this->error('❌ '.$result->getMessage());
                        if ($result->getErrorOutput()) {
                            $this->line('Error output:');
                            $this->line($result->getErrorOutput());
                        }

                        return Command::FAILURE;
                    }
                } else {
                    $this->info('Installation cancelled.');

                    return Command::SUCCESS;
                }
            }

            // Use the service for installation
            $packageName = $componentService->resolvePackageName($componentName);
            $this->info("Installing component: {$componentName}");
            $this->line("Package: {$packageName}");

            $options = [
                'force' => $force,
                'dev' => $dev,
            ];

            $result = $componentService->install($componentName, $options);

            if ($result->isSuccessful()) {
                $this->info('✅ '.$result->getMessage());

                // Show available commands hint
                $this->newLine();
                $this->line('💡 Component commands should now be available.');
                $this->line("   Run 'conduit list' to see all available commands.");

                return Command::SUCCESS;
            } else {
                $this->error('❌ '.$result->getMessage());
                if ($result->getErrorOutput()) {
                    $this->line('Error output:');
                    $this->line($result->getErrorOutput());
                }

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Installation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
