<?php

namespace App\Commands;

use App\Services\ComponentService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

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
                warning('The "know" component has been renamed to "knowledge".');
                info('This migration will:');
                info('  • Install jordanpartridge/conduit-knowledge globally');
                info('  • Remove jordanpartridge/conduit-know if installed');

                if (confirm('Continue with automatic migration to "knowledge"?', true)) {
                    $result = spin(
                        fn () => $componentService->migrateLegacyComponent('know', 'knowledge'),
                        'Migrating from know to knowledge...'
                    );

                    if ($result->isSuccessful()) {
                        info('✅ '.$result->getMessage());
                        info('💡 Component commands should now be available.');
                        info("   Run 'conduit list' to see all available commands.");

                        return Command::SUCCESS;
                    } else {
                        error('❌ '.$result->getMessage());
                        if ($result->getErrorOutput()) {
                            error('Error output:');
                            error($result->getErrorOutput());
                        }

                        return Command::FAILURE;
                    }
                } else {
                    info('Installation cancelled.');

                    return Command::SUCCESS;
                }
            }

            // Use the service for installation
            $packageName = $componentService->resolvePackageName($componentName);

            info("🔍 Installing component: {$componentName}");

            $options = [
                'force' => $force,
                'dev' => $dev,
            ];

            $result = spin(
                fn () => $componentService->install($componentName, $options),
                "Installing {$packageName}..."
            );

            if ($result->isSuccessful()) {
                info('✅ '.$result->getMessage());
                info("🎯 Run 'conduit list' to see available commands");

                return Command::SUCCESS;
            } else {
                error('❌ '.$result->getMessage());
                if ($result->getErrorOutput()) {
                    error($result->getErrorOutput());
                }

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            error('❌ Installation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
