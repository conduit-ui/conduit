<?php

namespace App\Services;

/**
 * Service to handle automatic migration from built-in knowledge to component
 */
class KnowledgeMigrationService
{
    private ComponentService $componentService;

    private bool $migrationCompleted = false;

    public function __construct(ComponentService $componentService)
    {
        $this->componentService = $componentService;
    }

    /**
     * Check and perform migration if needed - runs on every command
     */
    public function checkAndMigrate(): void
    {
        // Only run once per session
        if ($this->migrationCompleted) {
            return;
        }

        // Skip if already have new component
        if ($this->componentService->isInstalled('knowledge')) {
            $this->migrationCompleted = true;

            return;
        }

        // Check if old knowledge data exists
        if (! $this->hasOldKnowledgeData()) {
            $this->migrationCompleted = true;

            return;
        }

        // Perform automatic migration
        $this->performMigration();
        $this->migrationCompleted = true;
    }

    /**
     * Check if there's old knowledge data in the database
     */
    private function hasOldKnowledgeData(): bool
    {
        try {
            // Check common database locations
            $databases = [
                storage_path('conduit.sqlite'),
                $_SERVER['HOME'].'/.conduit/conduit.sqlite',
                base_path('conduit.sqlite'),
            ];

            foreach ($databases as $dbPath) {
                if (file_exists($dbPath)) {
                    // Check if database contains knowledge tables
                    $pdo = new \PDO("sqlite:$dbPath");
                    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%knowledge%' OR name LIKE '%entries%'");

                    if ($result && $result->fetch()) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Perform the automatic migration
     */
    private function performMigration(): void
    {
        try {
            echo "\n";
            echo "🔄 \033[33mDetected old knowledge data - migrating to improved component...\033[0m\n";
            echo "   Installing conduit-knowledge component...\n";

            $result = $this->componentService->install('knowledge');

            if ($result->isSuccessful()) {
                echo "✅ \033[32mKnowledge system upgraded successfully!\033[0m\n";
                echo "   Your knowledge data will be available via: \033[37mconduit knowledge\033[0m\n";
                echo "   Run \033[37mconduit knowledge --help\033[0m for new features.\n";
            } else {
                echo "⚠️  \033[33mAutomatic migration failed. You can install manually:\033[0m\n";
                echo "   \033[37mconduit install knowledge\033[0m\n";
            }
            echo "\n";
        } catch (\Exception $e) {
            // Fail silently - don't break commands
        }
    }
}
