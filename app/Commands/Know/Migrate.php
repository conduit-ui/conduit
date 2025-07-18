<?php

namespace App\Commands\Know;

use App\Services\DatabaseSchemaManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;

class Migrate extends Command
{
    protected $signature = 'know:migrate 
                            {--force : Force migration without confirmation}
                            {--check : Just check migration status}';

    protected $description = 'Migrate knowledge system from v1 to v2 graph schema';

    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->checkMigrationStatus();
        }

        return $this->runMigration();
    }

    private function checkMigrationStatus(): int
    {
        $this->info('🔍 Knowledge Schema Migration Status');
        $this->newLine();

        // Check current tables
        $hasKnowledgeEntries = Schema::hasTable('knowledge_entries');
        $hasKnowledgeTags = Schema::hasTable('knowledge_tags');
        $hasKnowledgeMetadata = Schema::hasTable('knowledge_metadata');
        $hasKnowledgeRelationships = Schema::hasTable('knowledge_relationships');

        $this->line('📋 Current Tables:');
        $this->line('  • knowledge_entries: '.($hasKnowledgeEntries ? '✅ EXISTS' : '❌ MISSING'));
        $this->line('  • knowledge_tags: '.($hasKnowledgeTags ? '✅ EXISTS' : '❌ MISSING'));
        $this->line('  • knowledge_metadata: '.($hasKnowledgeMetadata ? '✅ EXISTS' : '❌ MISSING'));
        $this->line('  • knowledge_relationships: '.($hasKnowledgeRelationships ? '✅ EXISTS' : '❌ MISSING'));

        $this->newLine();

        if ($hasKnowledgeEntries) {
            $entryCount = DB::table('knowledge_entries')->count();
            $this->line("📊 Current knowledge entries: {$entryCount}");

            // Check if we need migration
            $needsMigration = $hasKnowledgeEntries && ! $hasKnowledgeTags;

            if ($needsMigration) {
                $this->warn('⚠️  Migration needed: v1 schema detected');
                $this->line('💡 Run: conduit know:migrate --force');
            } else {
                $this->info('✅ Already using v2 graph schema');
            }
        } else {
            $this->line('🌱 No knowledge database found - will be created on first use');
        }

        return 0;
    }

    private function runMigration(): int
    {
        $this->info('🚀 Knowledge Graph Migration v1 → v2');
        $this->newLine();

        // Check if migration is needed
        $hasKnowledgeEntries = Schema::hasTable('knowledge_entries');
        $hasKnowledgeTags = Schema::hasTable('knowledge_tags');

        if (! $hasKnowledgeEntries) {
            $this->error('❌ No knowledge_entries table found');

            return 1;
        }

        if ($hasKnowledgeTags) {
            $this->info('✅ Already using v2 schema - migration not needed');

            return 0;
        }

        $entryCount = DB::table('knowledge_entries')->count();
        $this->line("📊 Found {$entryCount} knowledge entries to migrate");
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm("Migrate {$entryCount} entries from v1 to v2 schema?", true)) {
                $this->info('❌ Migration cancelled');

                return 0;
            }
        }

        try {
            $this->info('🔄 Starting migration...');
            $startTime = microtime(true);

            // Force migration
            $schemaManager = new DatabaseSchemaManager;
            $schemaManager->updateSchema();

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info("✅ Migration completed in {$duration} seconds!");

            // Verify migration
            return $this->verifyMigration($entryCount);

        } catch (\Exception $e) {
            $this->error("❌ Migration failed: {$e->getMessage()}");
            $this->line($e->getTraceAsString());

            return 1;
        }
    }

    private function verifyMigration(int $originalCount): int
    {
        $this->newLine();
        $this->info('🔍 Verifying migration results...');

        try {
            // Check new tables exist
            $tables = ['knowledge_entries', 'knowledge_tags', 'knowledge_entry_tags', 'knowledge_metadata', 'knowledge_relationships'];
            foreach ($tables as $table) {
                $exists = Schema::hasTable($table);
                $this->line("  • {$table}: ".($exists ? '✅ CREATED' : '❌ MISSING'));
            }

            // Check data counts
            $this->newLine();
            $this->line('📊 Migration Statistics:');

            $newEntryCount = DB::table('knowledge_entries')->count();
            $this->line("  • Knowledge entries: {$newEntryCount} (was {$originalCount})");

            if (Schema::hasTable('knowledge_tags')) {
                $tagCount = DB::table('knowledge_tags')->count();
                $this->line("  • Unique tags: {$tagCount}");
            }

            if (Schema::hasTable('knowledge_entry_tags')) {
                $relationCount = DB::table('knowledge_entry_tags')->count();
                $this->line("  • Tag relationships: {$relationCount}");
            }

            if (Schema::hasTable('knowledge_metadata')) {
                $metadataCount = DB::table('knowledge_metadata')->count();
                $this->line("  • Metadata entries: {$metadataCount}");
            }

            // Check backup table
            if (Schema::hasTable('knowledge_entries_v1_backup')) {
                $backupCount = DB::table('knowledge_entries_v1_backup')->count();
                $this->line("  • Backup entries: {$backupCount}");
            }

            $this->newLine();

            if ($newEntryCount === $originalCount) {
                $this->info('🎉 Migration successful! All data preserved.');
                $this->line('💡 Try: conduit know:list --limit=5');

                return 0;
            } else {
                $this->error("❌ Data loss detected! {$originalCount} → {$newEntryCount}");

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Verification failed: {$e->getMessage()}");

            return 1;
        }
    }
}
