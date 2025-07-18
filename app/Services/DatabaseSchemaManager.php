<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DatabaseSchemaManager
{
    /**
     * Ensure the database schema is created.
     * This is called automatically on first use for global installations.
     */
    public function ensureSchemaExists(): void
    {
        if (! $this->isDatabaseInitialized()) {
            $this->createSchema();
        }
    }

    /**
     * Check if the database has been initialized.
     */
    public function isDatabaseInitialized(): bool
    {
        try {
            return Schema::hasTable('knowledge_entries');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create the database schema programmatically.
     */
    public function createSchema(): void
    {
        // Create knowledge_entries table
        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->text('content'); // The knowledge/insight content
            $table->string('repo')->nullable(); // Git repository (e.g., 'conduit-ui/conduit')
            $table->string('branch')->nullable(); // Git branch name
            $table->string('commit_sha')->nullable(); // Git commit SHA
            $table->string('author')->nullable(); // Git author
            $table->string('project_type')->nullable(); // Detected project type (laravel-zero, etc.)
            $table->string('file_path')->nullable(); // Current file context (future)
            $table->json('tags')->nullable(); // Searchable tags
            $table->string('priority')->default('medium'); // Priority: low, medium, high
            $table->string('status')->default('open'); // Status: open, in-progress, completed, blocked
            $table->timestamps();

            // Indexes for search performance
            $table->index(['repo', 'branch']);
            $table->index('created_at');
            $table->index('author');
            $table->index('priority');
            $table->index('status');
        });

        // Create conduit_storage tables
        Schema::create('conduit_storage', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        // Create components table
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('version');
            $table->json('config')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Create settings table
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('type')->default('string'); // string, json, boolean, etc.
            $table->timestamps();
        });
    }

    /**
     * Get database file path - shared between local and global installations.
     * This ensures your personal knowledge base is consistent across all conduit instances.
     */
    public function getDatabasePath(): string
    {
        $userHome = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? sys_get_temp_dir();

        return $userHome.'/.conduit/conduit.sqlite';
    }

    /**
     * Initialize database for global installation.
     */
    public function initializeGlobalDatabase(): void
    {
        $dbPath = $this->getDatabasePath();
        $dbDir = dirname($dbPath);

        // Ensure directory exists
        if (! is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        // Create database file if it doesn't exist
        if (! file_exists($dbPath)) {
            touch($dbPath);
        }

        // Set proper permissions
        chmod($dbPath, 0644);

        // Ensure schema exists
        $this->ensureSchemaExists();
    }

    /**
     * Get database configuration for global installation.
     */
    public function getGlobalDatabaseConfig(): array
    {
        return [
            'driver' => 'sqlite',
            'database' => $this->getDatabasePath(),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
    }
}
