<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

/**
 * Fallback handler for legacy know commands
 *
 * Provides migration guidance and optional automatic migration
 * to the new conduit-knowledge component system.
 */
class KnowFallbackCommand extends Command
{
    protected $signature = 'know 
                            {action? : The know action you were trying to perform}
                            {--migrate : Automatically install conduit-knowledge component}';

    protected $description = 'Legacy know command - migrates to knowledge component';

    protected $hidden = true; // Hide from help listings

    public function handle(): int
    {
        $action = $this->argument('action');
        $autoMigrate = $this->option('migrate');
        $nonInteractive = $this->option('no-interaction');

        $this->showMigrationMessage($action);

        if ($autoMigrate || $this->shouldOfferMigration($nonInteractive)) {
            return $this->performMigration($action);
        }

        $this->showManualInstructions();

        return Command::FAILURE;
    }

    private function showMigrationMessage(?string $action): void
    {
        warning('The built-in "know" commands have been removed from Conduit core.');
        info('🚀 An improved knowledge system is now available as a component!');

        if ($action) {
            note("You tried to run: conduit know {$action}");
            note("New equivalent: conduit knowledge {$action}");
        } else {
            note('You tried to run: conduit know');
            note('New equivalent: conduit knowledge');
        }
    }

    private function shouldOfferMigration(bool $nonInteractive): bool
    {
        if ($this->isKnowledgeComponentInstalled()) {
            info('✅ conduit-knowledge component is already installed!');
            note('Try running your command again with "knowledge" instead of "know"');
            note('💡 If commands aren\'t working, try restarting your terminal or running:');
            note('composer global update jordanpartridge/conduit-knowledge');

            return false;
        }

        if ($nonInteractive) {
            info('💡 Add --migrate flag to automatically install the knowledge component');

            return false;
        }

        return confirm(
            'Would you like to install the conduit-knowledge component now?',
            true
        );
    }

    private function performMigration(?string $action): int
    {
        info('🔄 Installing conduit-knowledge component...');

        // Install globally using composer
        $process = new Process(['composer', 'global', 'require', 'jordanpartridge/conduit-knowledge']);
        $process->setTimeout(300);
        $process->run();

        if ($process->getExitCode() !== 0) {
            $errorOutput = $process->getErrorOutput();

            // Handle GitHub authentication issues
            if (str_contains($errorOutput, 'Could not authenticate against github.com')) {
                warning('🔐 GitHub authentication issue detected');
                info('🔄 Attempting to refresh GitHub authentication...');

                // Try to refresh GitHub auth
                $authProcess = new Process(['gh', 'auth', 'refresh']);
                $authProcess->run();

                if ($authProcess->getExitCode() === 0) {
                    info('✅ GitHub authentication refreshed, retrying installation...');

                    // Retry the installation
                    $retryProcess = new Process(['composer', 'global', 'require', 'jordanpartridge/conduit-knowledge']);
                    $retryProcess->setTimeout(300);
                    $retryProcess->run();

                    if ($retryProcess->getExitCode() === 0) {
                        info('✅ Successfully installed conduit-knowledge component after auth refresh!');

                        return $this->showSuccessMessage($action);
                    }
                }

                error('❌ Authentication refresh failed or installation still failed');
                note('💡 Try manually: gh auth login');
            } else {
                error('❌ Failed to install conduit-knowledge component');
                note('Error details: '.trim($errorOutput));
            }

            $this->showManualInstructions();

            return Command::FAILURE;
        }

        return $this->showSuccessMessage($action);
    }

    private function showSuccessMessage(?string $action): int
    {
        info('✅ Successfully installed conduit-knowledge component!');

        // Suggest running the new command
        info('🎉 Migration complete! You can now run:');
        if ($action) {
            note("conduit knowledge {$action}");
        } else {
            note('conduit knowledge');
        }

        note('💡 All knowledge commands are now available:');
        note('• conduit knowledge:add     - Add knowledge entries');
        note('• conduit knowledge:search  - Search knowledge base');
        note('• conduit knowledge:list    - List all entries');
        note('• conduit knowledge:delete  - Remove entries');
        note('• And more! Run "conduit list" to see all commands');

        return Command::SUCCESS;
    }

    private function showManualInstructions(): void
    {
        note('Manual Installation:');
        note('composer global require jordanpartridge/conduit-knowledge');

        note('Or use Conduit\'s install command:');
        note('conduit install knowledge');

        note('Quick migration (non-interactive):');
        note('conduit know --migrate --no-interaction');
    }

    private function isKnowledgeComponentInstalled(): bool
    {
        $process = new Process(['composer', 'global', 'show', 'jordanpartridge/conduit-knowledge']);
        $process->run();

        return $process->getExitCode() === 0;
    }
}
