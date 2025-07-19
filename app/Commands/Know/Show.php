<?php

namespace App\Commands\Know;

use App\Services\KnowledgeService;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;

class Show extends Command
{
    protected $signature = 'know:show 
                            {id : Knowledge entry ID to display}
                            {--json : Output as JSON}';

    protected $description = 'Display a specific knowledge entry with full details';

    public function handle(KnowledgeService $knowledgeService): int
    {
        if (! $this->isDatabaseReady()) {
            $this->error('❌ Knowledge database not initialized. Run: conduit know:add "first entry"');

            return 1;
        }

        return $this->showKnowledge($knowledgeService, (int) $this->argument('id'));
    }

    private function showKnowledge(KnowledgeService $knowledgeService, int $id): int
    {
        try {
            $entry = $knowledgeService->getEntry($id);

            if (! $entry) {
                $this->error("❌ Knowledge entry #{$id} not found");
                $this->line('💡 Use: conduit know:list (to see all entries)');
                $this->line('💡 Use: conduit know:search "query" (to find entries)');

                return 1;
            }

            if ($this->option('json')) {
                $this->line(json_encode($entry, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->displayFullEntry($knowledgeService, $entry);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error showing knowledge: {$e->getMessage()}");

            return 1;
        }
    }

    private function displayFullEntry(KnowledgeService $knowledgeService, $entry): void
    {
        $this->line("<options=bold>Knowledge Entry #{$entry->id}</>");
        $this->newLine();

        // Content
        $this->line('💡 <options=bold>Content:</>');
        $this->line("   {$entry->content}");
        $this->newLine();

        // Git Context
        if ($entry->repo || $entry->branch || $entry->commit_sha) {
            $this->line('📂 <options=bold>Git Context:</>');
            if ($entry->repo) {
                $this->line("   Repository: {$entry->repo}");
            }
            if ($entry->branch) {
                $this->line("   Branch: {$entry->branch}");
            }
            if ($entry->commit_sha) {
                $this->line("   Commit: {$entry->commit_sha}");
            }
            if ($entry->author) {
                $this->line("   Author: {$entry->author}");
            }
            $this->newLine();
        }

        // Project Info
        if ($entry->project_type) {
            $this->line("🔧 <options=bold>Project Type:</> {$entry->project_type}");
            $this->newLine();
        }

        // Tags (v2 schema)
        $tags = $entry->tags ?? [];
        if (! empty($tags)) {
            $this->line('🏷️  <options=bold>Tags:</>');
            foreach ($tags as $tag) {
                $this->line("   • {$tag}");
            }
            $this->newLine();
        }

        // TODO Status (v2 schema)
        $isTodo = in_array('todo', $tags);
        $priority = $entry->priority ?? 'medium';
        $status = $entry->status ?? 'open';

        if ($isTodo || $priority !== 'medium' || $status !== 'open') {
            $this->line('📋 <options=bold>Status Information:</>');

            if ($isTodo) {
                $this->line('   Type: TODO Item');
            }

            if ($priority !== 'medium') {
                $priorityIcon = match ($priority) {
                    'high' => '🔴',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪'
                };
                $this->line("   Priority: {$priorityIcon} {$priority}");
            }

            if ($status !== 'open') {
                $statusIcon = match ($status) {
                    'open' => '⭕',
                    'in-progress' => '🔄',
                    'completed' => '✅',
                    'blocked' => '🚫',
                    default => '📝'
                };
                $this->line("   Status: {$statusIcon} {$status}");
            }

            $this->newLine();
        }

        // Timestamps
        $this->line('📅 <options=bold>Timestamps:</>');
        if ($entry->created_at) {
            $createdAt = \Carbon\Carbon::parse($entry->created_at);
            $this->line("   Created: {$createdAt->format('Y-m-d H:i:s')} ({$createdAt->diffForHumans()})");
        }
        if ($entry->updated_at && $entry->updated_at !== $entry->created_at) {
            $updatedAt = \Carbon\Carbon::parse($entry->updated_at);
            $this->line("   Updated: {$updatedAt->format('Y-m-d H:i:s')} ({$updatedAt->diffForHumans()})");
        }
        $this->newLine();

        // Related Actions
        $this->line('🔗 <options=bold>Related Actions:</>');
        $this->line("   • conduit know:search \"{$this->getFirstWords($entry->content, 2)}\" (find similar)");

        if ($entry->repo) {
            $this->line("   • conduit know:search \"\" --repo=\"{$entry->repo}\" (same repo)");
        }

        if (! empty($tags)) {
            $firstTag = $tags[0];
            $this->line("   • conduit know:search \"\" --tags=\"{$firstTag}\" (same tag)");
        }
    }

    private function getFirstWords(string $text, int $count): string
    {
        $words = explode(' ', $text);

        return implode(' ', array_slice($words, 0, $count));
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
