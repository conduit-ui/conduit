<?php

namespace App\Commands\Know;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class SetupCommand extends Command
{
    protected $signature = 'know:setup 
                            {--uninstall : Remove git hooks}
                            {--force : Skip confirmations}';

    protected $description = 'Set up git auto-capture hooks for automatic knowledge collection';

    public function handle(): int
    {
        if ($this->option('uninstall')) {
            return $this->uninstallHooks();
        }

        return $this->installHooks();
    }

    private function installHooks(): int
    {
        info('🎣 Git Auto-Capture Setup');
        note('This will install global git hooks to automatically capture knowledge from commits.');

        if (! $this->option('force') && ! confirm('Install git auto-capture hooks?', true)) {
            info('❌ Setup cancelled');

            return 0;
        }

        try {
            // Create hooks directory
            $hooksDir = $this->getHooksDirectory();
            if (! is_dir($hooksDir)) {
                if (! mkdir($hooksDir, 0755, true)) {
                    throw new \Exception("Failed to create hooks directory: {$hooksDir}");
                }
            }

            // Configure git to use our hooks directory
            $this->runTask('Configuring git hooks directory', function () use ($hooksDir) {
                $process = new Process(['git', 'config', '--global', 'core.hooksPath', $hooksDir]);
                $process->run();

                return $process->isSuccessful();
            });

            // Create post-commit hook
            $this->runTask('Installing post-commit hook', function () use ($hooksDir) {
                return $this->createPostCommitHook($hooksDir);
            });

            // Make hooks executable
            $this->runTask('Making hooks executable', function () use ($hooksDir) {
                chmod($hooksDir.'/post-commit', 0755);

                return true;
            });

            $this->newLine();
            info('✅ Git auto-capture hooks installed successfully!');

            note('🚀 What happens next:');
            note('• Every git commit will automatically capture knowledge');
            note('• Knowledge includes commit message, changed files, and git context');
            note('• Auto-captured entries are tagged with "auto-capture" and "git-commit"');
            note('• You can search auto-captured knowledge with: conduit know:search --tags=auto-capture');

            note('💡 Pro tips:');
            note('• Use meaningful commit messages for better auto-capture');
            note('• Auto-capture is silent - no interruption to your workflow');
            note('• Run "conduit know:setup --uninstall" to remove hooks');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to install hooks: {$e->getMessage()}");

            return 1;
        }
    }

    private function uninstallHooks(): int
    {
        info('🗑️  Removing Git Auto-Capture Hooks');

        if (! $this->option('force') && ! confirm('Remove git auto-capture hooks?')) {
            info('❌ Uninstall cancelled');

            return 0;
        }

        try {
            // Remove git hooks configuration
            $this->runTask('Removing git hooks configuration', function () {
                $process = new Process(['git', 'config', '--global', '--unset', 'core.hooksPath']);
                $process->run();

                // Don't fail if config doesn't exist
                return true;
            });

            // Remove hooks directory
            $hooksDir = $this->getHooksDirectory();
            if (is_dir($hooksDir)) {
                $this->runTask('Removing hooks directory', function () use ($hooksDir) {
                    return $this->removeDirectory($hooksDir);
                });
            }

            $this->newLine();
            info('✅ Git auto-capture hooks removed successfully');
            note('Your existing knowledge entries are preserved');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to uninstall hooks: {$e->getMessage()}");

            return 1;
        }
    }

    private function createPostCommitHook(string $hooksDir): bool
    {
        $hookContent = <<<'BASH'
#!/bin/bash
# Conduit Knowledge Auto-Capture Hook
# Automatically captures knowledge from git commits

# Only run if conduit is available
if command -v conduit &> /dev/null; then
    # Run auto-capture in the background to avoid slowing down commits
    conduit know:auto-capture commit --quiet &
fi
BASH;

        $hookPath = $hooksDir.'/post-commit';

        return file_put_contents($hookPath, $hookContent) !== false;
    }

    private function getHooksDirectory(): string
    {
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '.';

        return $homeDir.'/.config/conduit/git-hooks';
    }

    private function removeDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    private function runTask(string $message, callable $callback): bool
    {
        $this->line("   {$message}...");

        try {
            $result = $callback();
            if ($result) {
                $this->line("   ✅ {$message}");
            } else {
                $this->line("   ❌ {$message}");
            }

            return (bool) $result;
        } catch (\Exception $e) {
            $this->line("   ❌ {$message}: {$e->getMessage()}");

            return false;
        }
    }
}
