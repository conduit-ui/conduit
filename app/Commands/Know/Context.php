<?php

namespace App\Commands\Know;

use App\Services\KnowledgeService;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;

class Context extends Command
{
    protected $signature = 'know:context 
                            {--repo= : Override repository context}
                            {--branch= : Override branch context}
                            {--json : Output as JSON}
                            {--limit=10 : Limit number of results}';

    protected $description = 'Show knowledge entries relevant to your current git context';

    public function handle(KnowledgeService $knowledgeService): int
    {
        if (! $this->isDatabaseReady()) {
            $this->error('❌ Knowledge database not initialized. Run: conduit know:add "first entry"');

            return 1;
        }

        return $this->showContextualKnowledge($knowledgeService);
    }

    private function showContextualKnowledge(KnowledgeService $knowledgeService): int
    {
        try {
            // Build context filters
            $filters = [
                'context' => true, // This enables repo preference ordering
                'limit' => (int) $this->option('limit'),
            ];

            // Allow manual override of context
            if ($this->option('repo')) {
                $filters['repo'] = $this->option('repo');
            }

            if ($this->option('branch')) {
                $filters['branch'] = $this->option('branch');
            }

            // Search with empty query but context filtering
            $entries = $knowledgeService->searchEntries('', $filters);

            if ($entries->isEmpty()) {
                $this->info('📍 No knowledge found for current context');
                $this->displayContextInfo($knowledgeService);
                $this->displayContextTips();

                return 0;
            }

            if ($this->option('json')) {
                $this->line(json_encode($entries, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->info("📍 Knowledge for current context ({$entries->count()} entries)");
            $this->displayContextInfo($knowledgeService);
            $this->newLine();

            foreach ($entries as $entry) {
                $this->displayEntry($entry);
                $this->newLine();
            }

            if ($entries->count() >= (int) $this->option('limit')) {
                $this->line('💡 Use --limit option to show more entries');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error showing context: {$e->getMessage()}");

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

    private function displayContextInfo(KnowledgeService $knowledgeService): void
    {
        // Get current git context using the service's method
        $gitContext = $this->getGitContext();

        if ($this->option('repo') || $this->option('branch')) {
            $this->line('🔧 Override Context:');
            if ($this->option('repo')) {
                $this->line("   Repository: {$this->option('repo')}");
            }
            if ($this->option('branch')) {
                $this->line("   Branch: {$this->option('branch')}");
            }
        } elseif ($gitContext['repo'] || $gitContext['branch']) {
            $this->line('📍 Current Context:');
            if ($gitContext['repo']) {
                $this->line("   Repository: {$gitContext['repo']}");
            }
            if ($gitContext['branch']) {
                $this->line("   Branch: {$gitContext['branch']}");
            }
            if ($gitContext['commit_sha']) {
                $this->line("   Commit: {$gitContext['commit_sha']}");
            }
        } else {
            $this->line('📍 Context: Not in a git repository');
        }
    }

    private function displayContextTips(): void
    {
        $this->newLine();
        $this->line('💡 Context tips:');
        $this->line('   • conduit know:context --repo=myproject (specific repo)');
        $this->line('   • conduit know:context --branch=main (specific branch)');
        $this->line('   • conduit know:add "your insight" (add to current context)');
        $this->line('   • conduit know:search "query" --context (context-aware search)');
    }

    private function getGitContext(): array
    {
        $context = [
            'repo' => null,
            'branch' => null,
            'commit_sha' => null,
            'author' => null,
            'project_type' => null,
        ];

        try {
            // Get repo name
            $remoteUrl = $this->runGitCommand(['git', 'remote', 'get-url', 'origin']);
            if ($remoteUrl && preg_match('#github\.com[/:]([^/]+/[^/]+?)(?:\.git)?/?$#', $remoteUrl, $matches)) {
                $context['repo'] = $matches[1];
            }

            $context['branch'] = $this->runGitCommand(['git', 'branch', '--show-current']);
            $context['commit_sha'] = substr($this->runGitCommand(['git', 'rev-parse', 'HEAD']) ?: '', 0, 7);
            $context['author'] = $this->runGitCommand(['git', 'config', 'user.name']);

        } catch (\Exception $e) {
            // Git context is optional
        }

        return $context;
    }

    private function runGitCommand(array $command): ?string
    {
        try {
            $process = new \Symfony\Component\Process\Process($command);
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
