<?php

namespace App\Commands;

use App\Services\ComponentService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;

/**
 * Command to help users migrate from built-in knowledge to conduit-knowledge component
 */
class MigrateKnowledgeCommand extends Command
{
    protected $signature = 'migrate:knowledge 
                            {--force : Skip confirmation prompts}';

    protected $description = 'Migrate from built-in knowledge system to conduit-knowledge component';

    public function handle(ComponentService $componentService): int
    {
        $this->info('🔄 Knowledge System Migration');
        $this->newLine();

        $this->warn('The built-in knowledge system has been removed from Conduit core.');
        $this->info('A much improved knowledge system is now available as a component:');
        $this->newLine();

        $this->line('✨ <fg=green>New conduit-knowledge component features:</>');
        $this->line('   • Enhanced search capabilities');
        $this->line('   • Better data organization');
        $this->line('   • Improved performance');
        $this->line('   • Semantic search support');
        $this->line('   • Git context integration');
        $this->newLine();

        // Check if already installed
        if ($componentService->isInstalled('knowledge')) {
            $this->info('✅ The conduit-knowledge component is already installed!');
            $this->line('   Run <fg=white>conduit knowledge --help</> to see available commands.');
            return Command::SUCCESS;
        }

        // Offer to install
        $force = $this->option('force');
        if (!$force) {
            $install = confirm('Would you like to install the new conduit-knowledge component now?', true);
            if (!$install) {
                $this->info('Migration cancelled. You can install it later with:');
                $this->line('   <fg=white>conduit install knowledge</>');
                return Command::SUCCESS;
            }
        }

        // Install the component
        $this->info('Installing conduit-knowledge component...');
        $result = $componentService->install('knowledge');

        if ($result->isSuccessful()) {
            $this->info('✅ ' . $result->getMessage());
            $this->newLine();
            $this->info('🎉 Migration complete! Knowledge commands are now available:');
            $this->line('   • <fg=white>conduit knowledge add</> - Add knowledge entries');
            $this->line('   • <fg=white>conduit knowledge search</> - Search your knowledge');
            $this->line('   • <fg=white>conduit knowledge list</> - List entries');
            $this->line('   • <fg=white>conduit knowledge show</> - Show entry details');
            $this->newLine();
            $this->line('💡 Run <fg=white>conduit knowledge --help</> for all available commands.');
            
            return Command::SUCCESS;
        } else {
            $this->error('❌ ' . $result->getMessage());
            if ($result->getErrorOutput()) {
                $this->line('Error details:');
                $this->line($result->getErrorOutput());
            }
            $this->newLine();
            $this->line('You can try installing manually with:');
            $this->line('   <fg=white>conduit install knowledge</>');
            
            return Command::FAILURE;
        }
    }
}