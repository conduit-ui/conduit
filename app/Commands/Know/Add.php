<?php

namespace App\Commands\Know;

use App\Services\DatabaseSchemaManager;
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;

class Add extends Command
{
    protected $signature = 'know:add 
                            {content : Knowledge content to capture}
                            {--tags= : Add tags (comma-separated)}
                            {--todo : Mark as TODO item}
                            {--priority=medium : Set priority (low/medium/high)}
                            {--status=open : Set status (open/in-progress/completed/blocked)}';

    protected $description = 'Add knowledge entry with automatic git context';

    public function handle(KnowledgeService $knowledgeService): int
    {
        if (! $this->isDatabaseReady()) {
            $this->info('🗄️ Initializing knowledge database...');
            $this->initializeDatabase();
        }

        return $this->captureKnowledge($knowledgeService, $this->argument('content'));
    }

    private function captureKnowledge(KnowledgeService $knowledgeService, string $content): int
    {
        try {
            // Prepare tags
            $tags = $this->option('tags') ? explode(',', $this->option('tags')) : [];
            if ($this->option('todo')) {
                $tags[] = 'todo';
            }
            $tags = array_map('trim', $tags);

            // Prepare metadata
            $metadata = [];
            if ($this->option('priority') !== 'medium') {
                $metadata['priority'] = $this->option('priority');
            }
            if ($this->option('status') !== 'open') {
                $metadata['status'] = $this->option('status');
            }

            // Use service to add entry
            $id = $knowledgeService->addEntry($content, $tags, $metadata);

            // Get full entry for display
            $entry = $knowledgeService->getEntry($id);

            $this->info("✅ Knowledge captured (ID: {$id})");

            if ($entry->repo) {
                $this->line("📍 Context: {$entry->repo} • {$entry->branch} • {$entry->commit_sha}");
            }

            if (! empty($entry->tagNames)) {
                $this->line('🏷️  Tags: '.implode(', ', $entry->tagNames));
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error capturing knowledge: {$e->getMessage()}");

            return 1;
        }
    }

    private function isDatabaseReady(): bool
    {
        try {
            return Schema::hasTable('knowledge_entries');
        } catch (\Exception $e) {
            return false;
        }
    }

    private function initializeDatabase(): void
    {
        $schemaManager = new DatabaseSchemaManager;
        $schemaManager->initializeGlobalDatabase();
        $this->info('🗄️ Knowledge database ready');
    }
}
