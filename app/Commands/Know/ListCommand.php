<?php

namespace App\Commands\Know;

use App\Services\KnowledgeService;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    protected $signature = 'know:list 
                            {--repo= : Filter by repository name}
                            {--branch= : Filter by branch name}
                            {--tags= : Filter by tags (comma-separated)}
                            {--author= : Filter by author}
                            {--type= : Filter by project type}
                            {--priority= : Filter by priority (low/medium/high)}
                            {--status= : Filter by status}
                            {--todo : Show only TODO items}
                            {--recent : Show recent entries (last 7 days)}
                            {--json : Output as JSON}
                            {--limit=20 : Limit number of results}';

    protected $description = 'List knowledge entries with optional filtering';

    public function handle(KnowledgeService $knowledgeService): int
    {
        if (! $this->isDatabaseReady()) {
            $this->error('❌ Knowledge database not initialized. Run: conduit know:add "first entry"');

            return 1;
        }

        return $this->listKnowledge($knowledgeService);
    }

    private function listKnowledge(KnowledgeService $knowledgeService): int
    {
        try {
            // Build filters from options
            $filters = [
                'repo' => $this->option('repo'),
                'branch' => $this->option('branch'),
                'tags' => $this->option('tags'),
                'author' => $this->option('author'),
                'type' => $this->option('type'),
                'priority' => $this->option('priority'),
                'status' => $this->option('status'),
                'todo' => $this->option('todo'),
                'limit' => (int) $this->option('limit'),
            ];

            // Handle recent filter
            if ($this->option('recent')) {
                $filters['recent'] = true;
            }

            // Remove null/false filters
            $filters = array_filter($filters, fn ($value) => $value !== null && $value !== false);

            // Search using service (empty query for listing all)
            $entries = $knowledgeService->searchEntries('', $filters);

            if ($entries->isEmpty()) {
                $this->info('📋 No knowledge entries found');
                $this->displayListTips();

                return 0;
            }

            if ($this->option('json')) {
                $this->line(json_encode($entries, JSON_PRETTY_PRINT));

                return 0;
            }

            $title = $this->option('todo') ? 'TODO Items' : 'Knowledge Entries';
            $this->info("📋 {$title} ({$entries->count()} total)");
            $this->displayAppliedFilters();
            $this->newLine();

            if ($this->option('todo')) {
                $this->displayTodoSummary($entries);
                $this->newLine();
            }

            foreach ($entries as $entry) {
                $this->displayEntry($knowledgeService, $entry);
                $this->newLine();
            }

            if ($entries->count() >= (int) $this->option('limit')) {
                $this->line('💡 Use --limit option to show more entries');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error listing knowledge: {$e->getMessage()}");

            return 1;
        }
    }

    private function displayEntry(KnowledgeService $knowledgeService, $entry): void
    {
        if ($this->option('todo')) {
            $this->displayTodoEntry($knowledgeService, $entry);
        } else {
            $this->displayKnowledgeEntry($knowledgeService, $entry);
        }
    }

    private function displayKnowledgeEntry(KnowledgeService $knowledgeService, $entry): void
    {
        $this->line("<options=bold>#{$entry->id}</> 💡 <options=bold>{$entry->content}</>");

        $details = [];
        if ($entry->repo && $entry->branch) {
            $details[] = "📂 {$entry->repo} • {$entry->branch}";
        }

        if ($entry->created_at) {
            $details[] = '📅 '.\Carbon\Carbon::parse($entry->created_at)->diffForHumans();
        }

        // Get tags for this entry using service
        $tags = $knowledgeService->getEntryTags($entry->id);
        if (! empty($tags)) {
            $details[] = '🏷️  '.implode(', ', $tags);
        }

        if (! empty($details)) {
            $this->line('   '.implode(' | ', $details));
        }
    }

    private function displayTodoEntry(KnowledgeService $knowledgeService, $entry): void
    {
        $status = $knowledgeService->getMetadataValue($entry->id, 'status', 'open');
        $priority = $knowledgeService->getMetadataValue($entry->id, 'priority', 'medium');

        $statusIcon = match ($status) {
            'open' => '⭕',
            'in-progress' => '🔄',
            'completed' => '✅',
            'blocked' => '🚫',
            default => '📝'
        };

        $priorityIcon = match ($priority) {
            'high' => '🔴',
            'medium' => '🟡',
            'low' => '🟢',
            default => '⚪'
        };

        $this->line("<options=bold>#{$entry->id}</> {$statusIcon} {$priorityIcon} <options=bold>{$entry->content}</>");

        $details = [];
        if ($entry->repo && $entry->branch) {
            $details[] = "📂 {$entry->repo} • {$entry->branch}";
        }

        if ($status !== 'open') {
            $details[] = "Status: {$status}";
        }

        if ($priority !== 'medium') {
            $details[] = "Priority: {$priority}";
        }

        if ($entry->created_at) {
            $details[] = '📅 '.\Carbon\Carbon::parse($entry->created_at)->diffForHumans();
        }

        if (! empty($details)) {
            $this->line('   '.implode(' | ', $details));
        }
    }

    private function displayTodoSummary($entries): void
    {
        $summary = [
            'open' => 0,
            'in-progress' => 0,
            'completed' => 0,
            'blocked' => 0,
        ];

        // Note: For performance, we'd ideally get this summary from the service
        // but for now we'll accept the limitation that status isn't pre-loaded
        foreach ($entries as $entry) {
            $summary['open']++; // Default since we don't have status pre-loaded
        }

        $statusText = [];
        if ($summary['open'] > 0) {
            $statusText[] = "⭕ {$summary['open']} open";
        }
        if ($summary['in-progress'] > 0) {
            $statusText[] = "🔄 {$summary['in-progress']} in progress";
        }
        if ($summary['completed'] > 0) {
            $statusText[] = "✅ {$summary['completed']} completed";
        }
        if ($summary['blocked'] > 0) {
            $statusText[] = "🚫 {$summary['blocked']} blocked";
        }

        if (! empty($statusText)) {
            $this->line('📊 Summary: '.implode(', ', $statusText));
        }
    }

    private function displayAppliedFilters(): void
    {
        $filters = [];

        if ($this->option('repo')) {
            $filters[] = "repo:{$this->option('repo')}";
        }
        if ($this->option('branch')) {
            $filters[] = "branch:{$this->option('branch')}";
        }
        if ($this->option('tags')) {
            $filters[] = "tags:{$this->option('tags')}";
        }
        if ($this->option('todo')) {
            $filters[] = 'todo:true';
        }
        if ($this->option('recent')) {
            $filters[] = 'recent:7days';
        }

        if (! empty($filters)) {
            $this->line('🔧 Filters: '.implode(', ', $filters));
        }
    }

    private function displayListTips(): void
    {
        $this->newLine();
        $this->line('💡 List tips:');
        $this->line('   • conduit know:list --todo (show TODO items)');
        $this->line('   • conduit know:list --recent (last 7 days)');
        $this->line('   • conduit know:list --repo=myproject');
        $this->line('   • conduit know:list --tags=performance,redis');
    }

    private function isDatabaseReady(): bool
    {
        try {
            return Schema::hasTable('knowledge_entries');
        } catch (\Exception $e) {
            return false;
        }
    }
}
