<?php

namespace App\Commands\Know;

use App\Services\KnowledgeService;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;

class Forget extends Command
{
    protected $signature = 'know:forget 
                            {id* : Knowledge entry ID(s) to remove}
                            {--force : Skip confirmation prompt}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Remove knowledge entries from your knowledge base';

    public function handle(KnowledgeService $knowledgeService): int
    {
        if (! $this->isDatabaseReady()) {
            $this->error('❌ Knowledge database not initialized. Run: conduit know:add "first entry"');

            return 1;
        }

        $ids = $this->argument('id');

        if (empty($ids)) {
            $this->error('❌ Please provide at least one knowledge entry ID');
            $this->line('💡 Use: conduit know:list (to see all entries)');

            return 1;
        }

        return $this->forgetKnowledge($knowledgeService, $ids);
    }

    private function forgetKnowledge(KnowledgeService $knowledgeService, array $ids): int
    {
        try {
            // Validate all IDs exist first
            $validIds = [];
            $entries = [];

            foreach ($ids as $id) {
                if (! is_numeric($id)) {
                    $this->error("❌ Invalid ID: {$id} (must be numeric)");

                    return 1;
                }

                $entry = $knowledgeService->getEntry((int) $id);

                if (! $entry) {
                    $this->error("❌ Knowledge entry #{$id} not found");

                    return 1;
                }

                $validIds[] = (int) $id;
                $entries[] = $entry;
            }

            // Show what will be deleted
            $this->info('🗑️  Knowledge entries to be removed:');
            $this->newLine();

            foreach ($entries as $entry) {
                $this->displayEntry($entry);
                $this->newLine();
            }

            if ($this->option('dry-run')) {
                $this->info('🔍 Dry run complete. No entries were actually deleted.');

                return 0;
            }

            // Confirmation
            $count = count($validIds);
            $confirmMessage = $count === 1
                ? 'Delete this knowledge entry?'
                : "Delete these {$count} knowledge entries?";

            if (! $this->option('force') && ! confirm($confirmMessage)) {
                $this->info('❌ Operation cancelled');

                return 0;
            }

            // Delete entries using service
            $deleted = 0;
            foreach ($validIds as $id) {
                if ($knowledgeService->deleteEntry($id)) {
                    $deleted++;
                }
            }

            if ($deleted === 0) {
                $this->error('❌ No entries were deleted');

                return 1;
            }

            $this->info("✅ Successfully deleted {$deleted} knowledge ".($deleted === 1 ? 'entry' : 'entries'));

            // Show some stats
            $totalEntries = \App\Models\KnowledgeEntry::count();
            $this->line("📊 Knowledge base now contains {$totalEntries} entries");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error removing knowledge: {$e->getMessage()}");

            return 1;
        }
    }

    private function displayEntry($entry): void
    {
        $this->line("<options=bold>#{$entry->id}</> 💡 <options=bold>{$entry->content}</>");

        $details = [];
        if ($entry->repo && $entry->branch) {
            $details[] = "📂 {$entry->repo} • {$entry->branch}";
        }

        if ($entry->created_at) {
            $details[] = '📅 '.\Carbon\Carbon::parse($entry->created_at)->diffForHumans();
        }

        // Get tags using service (v2 schema)
        $tags = $entry->tag_names ?? [];
        if (! empty($tags)) {
            $details[] = '🏷️  '.implode(', ', $tags);
        }

        if (! empty($details)) {
            $this->line('   '.implode(' | ', $details));
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
