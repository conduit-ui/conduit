<?php

namespace App\Commands\System;

use App\Services\ComponentManager;
use App\Services\SecurePackageInstaller;
use Illuminate\Console\Command;

class CleanupCommand extends Command
{
    protected $signature = 'system:cleanup 
                           {--components : Remove all installed components}
                           {--force : Skip confirmation prompts}
                           {--dev-only : Only available in development environment}';

    protected $description = 'Clean up development artifacts before committing (dev environment only)';

    public function handle(ComponentManager $componentManager, SecurePackageInstaller $installer): int
    {
        // Only allow in development environment
        if (config('app.env') !== 'development' && ! $this->option('dev-only')) {
            $this->error('❌ This command is only available in development environments');
            $this->info('💡 This prevents accidental cleanup in production');

            return 1;
        }

        $this->info('🧹 Development Environment Cleanup');
        $this->newLine();

        if ($this->option('components') || $this->confirm('Remove all installed components?', true)) {
            return $this->cleanupComponents($componentManager, $installer);
        }

        $this->info('✨ No cleanup actions selected');

        return 0;
    }

    private function cleanupComponents(ComponentManager $componentManager, SecurePackageInstaller $installer): int
    {
        $this->info('🔍 Scanning installed components...');

        $components = $componentManager->getInstalled();

        if (empty($components)) {
            $this->info('✅ No components installed - already clean!');

            return 0;
        }

        $this->newLine();
        $this->line('<options=bold>Found components:</options>');
        foreach ($components as $name => $config) {
            $package = $config['package'] ?? 'unknown';
            $this->line("   • {$name} ({$package})");
        }
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Remove all these components?', false)) {
            $this->info('🚫 Component cleanup cancelled');

            return 0;
        }

        $this->info('🗑️  Removing components...');
        $this->newLine();

        $removed = 0;
        $failed = 0;

        foreach ($components as $name => $config) {
            $package = $config['package'] ?? null;

            if (! $package) {
                $this->warn("⚠️  Skipping {$name} - no package name found");

                continue;
            }

            $this->line("   Removing {$name}...");

            try {
                // Remove from composer.json
                $result = $installer->remove($package);

                if ($result->isSuccessful()) {
                    // Remove from component registry
                    $componentManager->unregister($name);
                    $this->info("   ✅ Removed {$name}");
                    $removed++;
                } else {
                    $this->error("   ❌ Failed to remove {$name}: ".$result->getErrorOutput());
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error removing {$name}: ".$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();

        if ($removed > 0) {
            $this->info("✅ Successfully removed {$removed} component(s)");
        }

        if ($failed > 0) {
            $this->warn("⚠️  Failed to remove {$failed} component(s)");
        }

        // Final verification
        $this->info('🔍 Verifying cleanup...');
        $remainingComponents = $componentManager->getInstalled();

        if (empty($remainingComponents)) {
            $this->info('🎉 All components removed - codebase is clean for commit!');
            $this->newLine();
            $this->line('<options=bold>Safe to commit:</options>');
            $this->line('   • No component dependencies in composer.json');
            $this->line('   • Component registry cleared');
            $this->line('   • PHAR builds will be minimal');
        } else {
            $this->warn('⚠️  Some components remain installed');
            foreach ($remainingComponents as $name => $config) {
                $this->line("   • {$name}");
            }
        }

        return $failed > 0 ? 1 : 0;
    }
}
