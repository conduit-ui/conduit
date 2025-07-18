<?php

namespace App\Commands\Know;

use App\Models\KnowledgeEntry;
use App\Models\KnowledgeMetadata;
use App\Models\KnowledgeRelationship;
use App\Models\KnowledgeTag;
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\warning;

class OptimizeCommand extends Command
{
    protected $signature = 'know:optimize 
                            {--analyze : Show optimization analysis without making changes}
                            {--auto : Run all safe optimizations without prompts}';

    protected $description = 'Analyze and optimize the knowledge base for better performance and organization';

    public function handle(KnowledgeService $knowledgeService): int
    {
        if (! $this->isDatabaseReady()) {
            error('❌ Knowledge database not initialized. Run: conduit know:add "first entry"');

            return 1;
        }

        info('🔧 Knowledge Base Optimization');

        // Show stats first
        $this->showStatistics();

        // Analyze mode - show what would be done
        if ($this->option('analyze')) {
            return $this->analyzeOptimizations();
        }

        // Auto mode - run all safe optimizations
        if ($this->option('auto')) {
            return $this->runAutoOptimizations($knowledgeService);
        }

        // Interactive mode - let user choose what to optimize
        return $this->runInteractiveOptimizations($knowledgeService);
    }

    private function runInteractiveOptimizations(KnowledgeService $knowledgeService): int
    {
        $options = [
            'cleanup' => '🧹 Cleanup: Remove unused tags and orphaned data',
            'relationships' => '🔗 Relationships: Generate connections between related entries',
            'duplicates' => '🔍 Duplicates: Find and review potential duplicate entries',
            'analyze' => '📊 Analysis: Show detailed optimization recommendations',
        ];

        $selected = multiselect(
            'What optimizations would you like to run?',
            $options,
            ['cleanup', 'relationships']
        );

        if (empty($selected)) {
            warning('No optimizations selected. Exiting.');

            return 0;
        }

        foreach ($selected as $optimization) {
            match ($optimization) {
                'cleanup' => $this->cleanupUnusedData(),
                'relationships' => $this->generateRelationships($knowledgeService),
                'duplicates' => $this->findDuplicates(),
                'analyze' => $this->analyzeOptimizations(),
            };
        }

        info('✅ Knowledge base optimization complete!');

        return 0;
    }

    private function runAutoOptimizations(KnowledgeService $knowledgeService): int
    {
        info('🤖 Running automatic optimizations...');

        $this->cleanupUnusedData();
        $this->generateRelationships($knowledgeService);

        info('✅ Automatic optimization complete!');

        return 0;
    }

    private function showStatistics(): void
    {
        $this->info('📊 Knowledge Base Statistics');
        $this->newLine();

        // Basic counts
        $entryCount = KnowledgeEntry::count();
        $tagCount = KnowledgeTag::count();
        $metadataCount = KnowledgeMetadata::count();
        $relationshipCount = KnowledgeRelationship::count();

        $this->line("📚 Total Entries: {$entryCount}");
        $this->line("🏷️  Total Tags: {$tagCount}");
        $this->line("📝 Metadata Records: {$metadataCount}");
        $this->line("🔗 Relationships: {$relationshipCount}");
        $this->newLine();

        // Repository breakdown
        $repoStats = KnowledgeEntry::selectRaw('repo, COUNT(*) as count')
            ->whereNotNull('repo')
            ->groupBy('repo')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        if ($repoStats->isNotEmpty()) {
            $this->line('📂 Top Repositories:');
            foreach ($repoStats as $stat) {
                $this->line("   • {$stat->repo}: {$stat->count} entries");
            }
            $this->newLine();
        }

        // Popular tags
        $popularTags = KnowledgeTag::popular(5)->get();
        if ($popularTags->isNotEmpty()) {
            $this->line('🏷️  Popular Tags:');
            foreach ($popularTags as $tag) {
                $this->line("   • {$tag->name}: {$tag->usage_count} uses");
            }
            $this->newLine();
        }

        // Recent activity
        $recentCount = KnowledgeEntry::recent(7)->count();
        $this->line("🕒 Recent Activity (7 days): {$recentCount} entries");
        $this->newLine();
    }

    private function analyzeOptimizations(): int
    {
        $this->info('🔍 Optimization Analysis');
        $this->newLine();

        // Unused tags
        $unusedTags = KnowledgeTag::unused()->count();
        if ($unusedTags > 0) {
            $this->line("🧹 Cleanup: {$unusedTags} unused tags can be removed");
        }

        // Orphaned metadata
        $orphanedMetadata = KnowledgeMetadata::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('knowledge_entries')
                ->whereColumn('knowledge_entries.id', 'knowledge_metadata.entry_id');
        })->count();

        if ($orphanedMetadata > 0) {
            $this->line("🧹 Cleanup: {$orphanedMetadata} orphaned metadata records can be removed");
        }

        // Missing relationships
        $entriesWithTags = KnowledgeEntry::has('tags')->count();
        $existingRelationships = KnowledgeRelationship::count();
        $potentialRelationships = max(0, ($entriesWithTags * ($entriesWithTags - 1)) / 2 - $existingRelationships);

        if ($potentialRelationships > 0) {
            $this->line("🔗 Relationships: ~{$potentialRelationships} potential relationships could be generated");
        }

        // Potential duplicates (same content length and similar words)
        $duplicateCandidates = KnowledgeEntry::selectRaw('LENGTH(content) as len, COUNT(*) as count')
            ->groupBy('len')
            ->having('count', '>', 1)
            ->count();

        if ($duplicateCandidates > 0) {
            $this->line("🔍 Duplicates: {$duplicateCandidates} potential duplicate groups found");
        }

        if ($unusedTags === 0 && $orphanedMetadata === 0 && $potentialRelationships === 0 && $duplicateCandidates === 0) {
            $this->info('✨ Your knowledge base is already well optimized!');
        } else {
            $this->newLine();
            $this->info('💡 Run optimizations:');
            $this->line('   • conduit know:optimize --cleanup (remove unused data)');
            $this->line('   • conduit know:optimize --relationships (generate connections)');
            $this->line('   • conduit know:optimize --dedupe (find duplicates)');
            $this->line('   • conduit know:optimize --all (run everything)');
        }

        return 0;
    }

    private function cleanupUnusedData(): void
    {
        $this->info('🧹 Cleaning up unused data...');

        // Remove unused tags
        $unusedTags = KnowledgeTag::unused()->get();
        $tagCount = $unusedTags->count();

        if ($tagCount > 0) {
            KnowledgeTag::unused()->delete();
            $this->line("   ✅ Removed {$tagCount} unused tags");
        }

        // Remove orphaned metadata
        $orphanedCount = KnowledgeMetadata::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('knowledge_entries')
                ->whereColumn('knowledge_entries.id', 'knowledge_metadata.entry_id');
        })->delete();

        if ($orphanedCount > 0) {
            $this->line("   ✅ Removed {$orphanedCount} orphaned metadata records");
        }

        // Remove orphaned relationships
        $orphanedRelationships = KnowledgeRelationship::where(function ($query) {
            $query->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('knowledge_entries')
                    ->whereColumn('knowledge_entries.id', 'knowledge_relationships.from_entry_id');
            })->orWhereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('knowledge_entries')
                    ->whereColumn('knowledge_entries.id', 'knowledge_relationships.to_entry_id');
            });
        })->delete();

        if ($orphanedRelationships > 0) {
            $this->line("   ✅ Removed {$orphanedRelationships} orphaned relationships");
        }

        if ($tagCount === 0 && $orphanedCount === 0 && $orphanedRelationships === 0) {
            $this->line('   ✨ No cleanup needed - everything is already tidy!');
        }

        $this->newLine();
    }

    private function generateRelationships(KnowledgeService $knowledgeService): void
    {
        $this->info('🔗 Generating missing relationships...');

        $entries = KnowledgeEntry::with('tags')->get();
        $relationshipsCreated = 0;

        foreach ($entries as $entry) {
            if ($entry->tags->isEmpty()) {
                continue;
            }

            // Find related entries based on shared tags
            $relatedEntries = KnowledgeEntry::withDetails()
                ->relatedTo($entry)
                ->limit(5)
                ->get();

            foreach ($relatedEntries as $relatedEntry) {
                // Check if relationship already exists
                $exists = KnowledgeRelationship::where(function ($query) use ($entry, $relatedEntry) {
                    $query->where('from_entry_id', $entry->id)
                        ->where('to_entry_id', $relatedEntry->id);
                })->orWhere(function ($query) use ($entry, $relatedEntry) {
                    $query->where('from_entry_id', $relatedEntry->id)
                        ->where('to_entry_id', $entry->id);
                })->exists();

                if (! $exists) {
                    KnowledgeRelationship::createOrUpdateRelationship(
                        $entry->id,
                        $relatedEntry->id,
                        'related_to'
                    );
                    $relationshipsCreated++;
                }
            }
        }

        $this->line("   ✅ Created {$relationshipsCreated} new relationships");
        $this->newLine();
    }

    private function findDuplicates(): void
    {
        $this->info('🔍 Finding potential duplicates...');

        // Group by similar content length and find potential duplicates
        $candidates = KnowledgeEntry::selectRaw('id, content, LENGTH(content) as len')
            ->get()
            ->groupBy('len')
            ->filter(function ($group) {
                return $group->count() > 1;
            });

        $duplicateGroups = 0;

        foreach ($candidates as $lengthGroup) {
            $entries = $lengthGroup->toArray();

            for ($i = 0; $i < count($entries); $i++) {
                for ($j = $i + 1; $j < count($entries); $j++) {
                    $similarity = $this->calculateSimilarity($entries[$i]['content'], $entries[$j]['content']);

                    if ($similarity > 0.8) { // 80% similarity threshold
                        $duplicateGroups++;
                        $this->line('   🔍 Potential duplicates (similarity: '.round($similarity * 100).'%):');
                        $this->line("      #{$entries[$i]['id']}: ".substr($entries[$i]['content'], 0, 50).'...');
                        $this->line("      #{$entries[$j]['id']}: ".substr($entries[$j]['content'], 0, 50).'...');

                        if (confirm('Would you like to review these entries now?', false)) {
                            $this->call('know:show', ['id' => $entries[$i]['id']]);
                            $this->newLine();
                            $this->call('know:show', ['id' => $entries[$j]['id']]);

                            if (confirm("Delete entry #{$entries[$j]['id']} (keep #{$entries[$i]['id']})?", false)) {
                                $this->call('know:forget', ['id' => [$entries[$j]['id']], '--force' => true]);
                                info("✅ Deleted duplicate entry #{$entries[$j]['id']}");
                            }
                        }
                        $this->newLine();
                    }
                }
            }
        }

        if ($duplicateGroups === 0) {
            $this->line('   ✨ No potential duplicates found!');
        } else {
            $this->line("   ⚠️  Found {$duplicateGroups} potential duplicate groups");
            $this->line('   💡 Review and manually merge using conduit know:forget if needed');
        }

        $this->newLine();
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Simple word-based similarity calculation
        $words1 = array_unique(str_word_count(strtolower($str1), 1));
        $words2 = array_unique(str_word_count(strtolower($str2), 1));

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        if (count($union) === 0) {
            return 0;
        }

        return count($intersection) / count($union);
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
