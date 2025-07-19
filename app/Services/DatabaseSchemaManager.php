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
        } else {
            $this->updateSchema();
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
     * Update existing schema by creating v2 graph structure and migrating data.
     */
    public function updateSchema(): void
    {
        try {
            // Check if we need to migrate to v2 graph schema
            if (Schema::hasTable('knowledge_entries') && ! Schema::hasTable('knowledge_tags')) {
                $this->migrateToV2Table();
            }
        } catch (\Exception $e) {
            // Silently fail if we can't update schema
            // The commands will handle missing columns gracefully
        }
    }

    /**
     * Migrate from v1 to v2 knowledge graph schema.
     */
    private function migrateToV2Table(): void
    {
        // Create v2 knowledge graph schema
        $this->createV2GraphSchema();

        // Migrate existing data
        if (Schema::hasTable('knowledge_entries')) {
            $this->migrateDataToV2Schema();
        }

        // Rename tables: backup old, promote new
        Schema::rename('knowledge_entries', 'knowledge_entries_v1_backup');
        Schema::rename('knowledge_entries_v2', 'knowledge_entries');
        Schema::rename('knowledge_tags_v2', 'knowledge_tags');
        Schema::rename('knowledge_entry_tags_v2', 'knowledge_entry_tags');
        Schema::rename('knowledge_metadata_v2', 'knowledge_metadata');
        Schema::rename('knowledge_relationships_v2', 'knowledge_relationships');
    }

    /**
     * Create v2 knowledge graph schema.
     */
    private function createV2GraphSchema(): void
    {
        // Core knowledge entries (lean and focused)
        Schema::create('knowledge_entries_v2', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->string('repo')->nullable();
            $table->string('branch')->nullable();
            $table->string('commit_sha')->nullable();
            $table->string('author')->nullable();
            $table->string('project_type')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['repo', 'branch']);
            $table->index('created_at');
            $table->index('author');
            $table->index('project_type');
        });

        // Normalized tags (reusable, searchable)
        Schema::create('knowledge_tags_v2', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index('name');
            $table->index('usage_count');
        });

        // Many-to-many: entries <-> tags
        Schema::create('knowledge_entry_tags_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id');
            $table->foreignId('tag_id');
            $table->timestamps();

            $table->unique(['entry_id', 'tag_id']);
            $table->index('entry_id');
            $table->index('tag_id');
        });

        // Flexible metadata (priority, status, custom fields)
        Schema::create('knowledge_metadata_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id');
            $table->string('key'); // 'priority', 'status', 'difficulty', etc.
            $table->text('value');
            $table->string('type')->default('string'); // 'string', 'integer', 'boolean', 'json'
            $table->timestamps();

            $table->index(['entry_id', 'key']);
            $table->index('key');
        });

        // Knowledge relationships (the graph!)
        Schema::create('knowledge_relationships_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_entry_id');
            $table->foreignId('to_entry_id');
            $table->string('relationship_type'); // 'depends_on', 'conflicts_with', 'extends', 'implements', 'fixes', 'relates_to'
            $table->float('strength')->default(1.0); // 0.0 to 1.0, for ranking relationships
            $table->boolean('auto_detected')->default(false); // Was this relationship auto-detected?
            $table->timestamps();

            $table->index(['from_entry_id', 'relationship_type']);
            $table->index(['to_entry_id', 'relationship_type']);
            $table->index('relationship_type');
        });
    }

    /**
     * Migrate existing data to v2 schema.
     */
    private function migrateDataToV2Schema(): void
    {
        $oldEntries = \DB::table('knowledge_entries')->get();

        foreach ($oldEntries as $entry) {
            // Insert core entry
            $entryId = \DB::table('knowledge_entries_v2')->insertGetId([
                'id' => $entry->id,
                'content' => $entry->content,
                'repo' => $entry->repo,
                'branch' => $entry->branch,
                'commit_sha' => $entry->commit_sha,
                'author' => $entry->author,
                'project_type' => $entry->project_type,
                'file_path' => $entry->file_path ?? null,
                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ]);

            // Migrate tags from JSON to normalized structure
            if ($entry->tags) {
                $tags = json_decode($entry->tags, true);
                if (is_array($tags)) {
                    foreach ($tags as $tagName) {
                        $tagName = trim($tagName);
                        if (empty($tagName)) {
                            continue;
                        }

                        // Get or create tag
                        $tagId = \DB::table('knowledge_tags_v2')->where('name', $tagName)->value('id');
                        if (! $tagId) {
                            $tagId = \DB::table('knowledge_tags_v2')->insertGetId([
                                'name' => $tagName,
                                'usage_count' => 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            \DB::table('knowledge_tags_v2')->where('id', $tagId)->increment('usage_count');
                        }

                        // Link entry to tag
                        \DB::table('knowledge_entry_tags_v2')->insert([
                            'entry_id' => $entryId,
                            'tag_id' => $tagId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Migrate priority and status to metadata
            if (isset($entry->priority) && $entry->priority !== 'medium') {
                \DB::table('knowledge_metadata_v2')->insert([
                    'entry_id' => $entryId,
                    'key' => 'priority',
                    'value' => $entry->priority,
                    'type' => 'string',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (isset($entry->status) && $entry->status !== 'open') {
                \DB::table('knowledge_metadata_v2')->insert([
                    'entry_id' => $entryId,
                    'key' => 'status',
                    'value' => $entry->status,
                    'type' => 'string',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
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
