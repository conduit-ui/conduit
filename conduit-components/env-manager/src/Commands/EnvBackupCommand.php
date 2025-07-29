<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitEnvmanager\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;

class EnvBackupCommand extends Command
{
    protected $signature = 'env:backup {--name= : Backup file name} {--restore : Restore from backup}';

    protected $description = 'Backup or restore .env file';

    public function handle(): int
    {
        if ($this->option('restore')) {
            return $this->restoreBackup();
        }

        return $this->createBackup();
    }

    private function createBackup(): int
    {
        $envPath = getcwd() . '/.env';

        if (!file_exists($envPath)) {
            $this->error('❌ .env file not found');
            return 1;
        }

        $backupName = $this->option('name') ?: date('Y-m-d_H-i-s');
        $backupPath = getcwd() . "/.env.backup.{$backupName}";

        if (file_exists($backupPath)) {
            if (!confirm("Backup file {$backupName} already exists. Overwrite?", false)) {
                $this->info('Operation cancelled');
                return 0;
            }
        }

        if (!copy($envPath, $backupPath)) {
            $this->error('❌ Failed to create backup');
            return 1;
        }

        $this->info("✅ Created backup: .env.backup.{$backupName}");
        return 0;
    }

    private function restoreBackup(): int
    {
        $envPath = getcwd() . '/.env';
        $backupPattern = getcwd() . '/.env.backup.*';
        $backupFiles = glob($backupPattern);

        if (empty($backupFiles)) {
            $this->error('❌ No backup files found');
            return 1;
        }

        // Show available backups
        $this->info('Available backups:');
        foreach ($backupFiles as $index => $file) {
            $name = basename($file);
            $date = date('Y-m-d H:i:s', filemtime($file));
            $this->line("  [{$index}] {$name} (created: {$date})");
        }

        $choice = $this->ask('Select backup number to restore');
        
        if (!is_numeric($choice) || !isset($backupFiles[$choice])) {
            $this->error('❌ Invalid selection');
            return 1;
        }

        $selectedBackup = $backupFiles[$choice];

        // Confirm if .env exists
        if (file_exists($envPath)) {
            if (!confirm('⚠️  This will overwrite your current .env file. Continue?', false)) {
                $this->info('Operation cancelled');
                return 0;
            }
        }

        if (!copy($selectedBackup, $envPath)) {
            $this->error('❌ Failed to restore backup');
            return 1;
        }

        $this->info('✅ Successfully restored backup');
        return 0;
    }
}