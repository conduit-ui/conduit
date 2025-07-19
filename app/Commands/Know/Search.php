<?php

namespace App\Commands\Know;

use App\Services\KnowledgeService;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class Search extends Command
{
    protected $signature = 'know:search 
                            {query : Search query for knowledge entries}
                            {--repo= : Filter by repository name}
                            {--branch= : Filter by branch name}
                            {--tags= : Filter by tags (comma-separated)}
                            {--author= : Filter by author}
                            {--type= : Filter by project type}
                            {--priority= : Filter by priority (low/medium/high)}
                            {--status= : Filter by status}
                            {--todo : Show only TODO items}
                            {--context : Include context from current repository}
                            {--json : Output as JSON}
                            {--limit=10 : Limit number of results}';

    protected $description = 'Search knowledge entries with advanced filtering';

    public function handle(KnowledgeService $knowledgeService): int
    {
        if (! $this->isDatabaseReady()) {
            $this->error('❌ Knowledge database not initialized. Run: conduit know:add "first entry"');

            return 1;
        }

        return $this->searchKnowledge($knowledgeService, $this->argument('query'));
    }

    private function searchKnowledge(KnowledgeService $knowledgeService, string $query): int
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
                'context' => $this->option('context'),
                'limit' => (int) $this->option('limit'),
            ];

            // Remove null/false filters
            $filters = array_filter($filters, fn ($value) => $value !== null && $value !== false);

            // Search using service
            $entries = $knowledgeService->searchEntries($query, $filters);

            if ($entries->isEmpty()) {
                $this->info("🔍 No knowledge found for: {$query}");
                $this->displaySearchTips();

                return 0;
            }

            if ($this->option('json')) {
                $this->line(json_encode($entries, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->info("🔍 Found {$entries->count()} entries for: {$query}");
            $this->displayAppliedFilters();
            $this->newLine();

            foreach ($entries as $entry) {
                $this->displayEntry($entry);
                $this->newLine();
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error searching knowledge: {$e->getMessage()}");

            return 1;
        }
    }

    private function displayEntry($entry): void
    {
        // Show ID for reference
        $this->line("<options=bold>#{$entry->id}</> 💡 <options=bold>{$entry->content}</>");

        $details = [];
        if ($entry->repo && $entry->branch) {
            $details[] = "📂 {$entry->repo} • {$entry->branch}";
        }

        if ($entry->created_at) {
            $details[] = '📅 '.\Carbon\Carbon::parse($entry->created_at)->diffForHumans();
        }

        // Get tags from preloaded relationship
        $tags = $entry->tagNames ?? [];
        if (! empty($tags)) {
            $details[] = '🏷️  '.implode(', ', $tags);
        }

        // Get priority and status from preloaded metadata
        $priority = $entry->priority ?? 'medium';
        if ($priority && $priority !== 'medium') {
            $priorityIcon = match ($priority) {
                'high' => '🔴',
                'low' => '🟢',
                default => '⚪'
            };
            $details[] = "{$priorityIcon} {$priority}";
        }

        $status = $entry->status ?? 'open';
        if ($status && $status !== 'open') {
            $statusIcon = match ($status) {
                'in-progress' => '🔄',
                'completed' => '✅',
                'blocked' => '🚫',
                default => '📝'
            };
            $details[] = "{$statusIcon} {$status}";
        }

        if (! empty($details)) {
            $this->line('   '.implode(' | ', $details));
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
        if ($this->option('context')) {
            $filters[] = 'context:current';
        }

        if (! empty($filters)) {
            $this->line('🔧 Filters: '.implode(', ', $filters));
        }
    }

    private function displaySearchTips(): void
    {
        $this->newLine();
        $this->line('💡 Search tips:');
        $this->line('   • conduit know:search "redis" --tags=performance');
        $this->line('   • conduit know:search "bug" --repo=myproject');
        $this->line('   • conduit know:search "auth" --context');
        $this->line('   • conduit know:search "todo" --todo');
    }

    private function getGitContext(): array
    {
        $context = ['repo' => null, 'branch' => null];

        try {
            $remoteUrl = $this->runGitCommand(['git', 'remote', 'get-url', 'origin']);
            if ($remoteUrl && preg_match('#github\.com[/:]([^/]+/[^/]+?)(?:\.git)?/?$#', $remoteUrl, $matches)) {
                $context['repo'] = $matches[1];
            }
            $context['branch'] = $this->runGitCommand(['git', 'branch', '--show-current']);
        } catch (\Exception $e) {
            // Context is optional
        }

        return $context;
    }

    private function runGitCommand(array $command): ?string
    {
        try {
            $process = new Process($command);
            $process->run();

            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (\Exception $e) {
            return null;
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
}
