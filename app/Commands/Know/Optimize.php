<?php

namespace App\Commands\Know;

use App\Models\Knowledge\Entry;
use App\Models\Knowledge\Metadata;
use App\Models\Knowledge\Relationship;
use App\Models\Knowledge\Tag;
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\warning;

class Optimize extends Command
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
        $entryCount = Entry::count();
        $tagCount = Tag::count();
        $metadataCount = Metadata::count();
        $relationshipCount = Relationship::count();

        $this->line("📚 Total Entries: {$entryCount}");
        $this->line("🏷️  Total Tags: {$tagCount}");
        $this->line("📝 Metadata Records: {$metadataCount}");
        $this->line("🔗 Relationships: {$relationshipCount}");
        $this->newLine();

        // Repository breakdown
        $repoStats = Entry::selectRaw('repo, COUNT(*) as count')
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
        $popularTags = Tag::popular(5)->get();
        if ($popularTags->isNotEmpty()) {
            $this->line('🏷️  Popular Tags:');
            foreach ($popularTags as $tag) {
                $this->line("   • {$tag->name}: {$tag->usage_count} uses");
            }
            $this->newLine();
        }

        // Recent activity
        $recentCount = Entry::recent(7)->count();
        $this->line("🕒 Recent Activity (7 days): {$recentCount} entries");
        $this->newLine();
    }

    private function analyzeOptimizations(): int
    {
        $this->info('🔍 Optimization Analysis');
        $this->newLine();

        // Unused tags
        $unusedTags = Tag::unused()->count();
        if ($unusedTags > 0) {
            $this->line("🧹 Cleanup: {$unusedTags} unused tags can be removed");
        }

        // Orphaned metadata
        $orphanedMetadata = Metadata::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('knowledge_entries')
                ->whereColumn('knowledge_entries.id', 'knowledge_metadata.entry_id');
        })->count();

        if ($orphanedMetadata > 0) {
            $this->line("🧹 Cleanup: {$orphanedMetadata} orphaned metadata records can be removed");
        }

        // Missing relationships
        $entriesWithTags = Entry::has('tags')->count();
        $existingRelationships = Relationship::count();
        $potentialRelationships = max(0, ($entriesWithTags * ($entriesWithTags - 1)) / 2 - $existingRelationships);

        if ($potentialRelationships > 0) {
            $this->line("🔗 Relationships: ~{$potentialRelationships} potential relationships could be generated");
        }

        // Potential duplicates (same content length and similar words)
        $duplicateCandidates = Entry::selectRaw('LENGTH(content) as len, COUNT(*) as count')
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
        $unusedTags = Tag::unused()->get();
        $tagCount = $unusedTags->count();

        if ($tagCount > 0) {
            Tag::unused()->delete();
            $this->line("   ✅ Removed {$tagCount} unused tags");
        }

        // Remove orphaned metadata
        $orphanedCount = Metadata::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('knowledge_entries')
                ->whereColumn('knowledge_entries.id', 'knowledge_metadata.entry_id');
        })->delete();

        if ($orphanedCount > 0) {
            $this->line("   ✅ Removed {$orphanedCount} orphaned metadata records");
        }

        // Remove orphaned relationships
        $orphanedRelationships = Relationship::where(function ($query) {
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

        $entries = Entry::with('tags')->get();
        $relationshipsCreated = 0;

        foreach ($entries as $entry) {
            if ($entry->tags->isEmpty()) {
                continue;
            }

            // Find related entries based on shared tags
            $relatedEntries = Entry::withDetails()
                ->relatedTo($entry)
                ->limit(5)
                ->get();

            foreach ($relatedEntries as $relatedEntry) {
                // Check if relationship already exists
                $exists = Relationship::where(function ($query) use ($entry, $relatedEntry) {
                    $query->where('from_entry_id', $entry->id)
                        ->where('to_entry_id', $relatedEntry->id);
                })->orWhere(function ($query) use ($entry, $relatedEntry) {
                    $query->where('from_entry_id', $relatedEntry->id)
                        ->where('to_entry_id', $entry->id);
                })->exists();

                if (! $exists) {
                    Relationship::createOrUpdateRelationship(
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

        // Get all entries with improved grouping (length ranges instead of exact length)
        $entries = Entry::withDetails()->get();

        $duplicateGroups = 0;
        $processedPairs = [];

        foreach ($entries as $i => $entry1) {
            foreach ($entries as $j => $entry2) {
                // Skip same entry and already processed pairs
                if ($i >= $j || isset($processedPairs["{$entry1->id}-{$entry2->id}"])) {
                    continue;
                }

                // Mark this pair as processed
                $processedPairs["{$entry1->id}-{$entry2->id}"] = true;
                $processedPairs["{$entry2->id}-{$entry1->id}"] = true;

                // Check if entries are in similar length range (±20%)
                $len1 = strlen($entry1->content);
                $len2 = strlen($entry2->content);
                $lengthRatio = min($len1, $len2) / max($len1, $len2);

                if ($lengthRatio < 0.8) {
                    continue;
                }

                // Calculate multiple similarity metrics
                $similarities = $this->calculateAdvancedSimilarity($entry1->content, $entry2->content);

                // Combined similarity score with weights
                $combinedSimilarity =
                    $similarities['jaccard'] * 0.4 +      // Word overlap
                    $similarities['levenshtein'] * 0.3 +  // Character similarity
                    $similarities['semantic'] * 0.3;      // Semantic similarity

                if ($combinedSimilarity > 0.75) { // 75% combined similarity threshold
                    $duplicateGroups++;
                    $this->displayDuplicateCandidate($entry1, $entry2, $similarities, $combinedSimilarity);
                }
            }
        }

        if ($duplicateGroups === 0) {
            $this->line('   ✨ No potential duplicates found!');
        } else {
            $this->line("   ⚠️  Found {$duplicateGroups} potential duplicate groups");
            $this->line('   💡 Use --dry-run to analyze without prompting for deletion');
        }

        $this->newLine();
    }

    private function displayDuplicateCandidate($entry1, $entry2, $similarities, $combinedSimilarity): void
    {
        $this->line('   🔍 Potential duplicates (combined similarity: '.round($combinedSimilarity * 100).'%):');
        $this->line("      #{$entry1->id}: ".substr($entry1->content, 0, 50).'...');
        $this->line("      #{$entry2->id}: ".substr($entry2->content, 0, 50).'...');

        // Show similarity breakdown
        $this->line('      📊 Similarity breakdown:');
        $this->line('         Words: '.round($similarities['jaccard'] * 100).'%');
        $this->line('         Characters: '.round($similarities['levenshtein'] * 100).'%');
        $this->line('         Semantic: '.round($similarities['semantic'] * 100).'%');

        // Show metadata differences
        $this->displayMetadataDifferences($entry1, $entry2);

        if (! $this->option('dry-run') && confirm('Would you like to review these entries now?', false)) {
            $this->call('know:show', ['id' => $entry1->id]);
            $this->newLine();
            $this->call('know:show', ['id' => $entry2->id]);

            $older = $entry1->created_at < $entry2->created_at ? $entry1 : $entry2;
            $newer = $entry1->created_at >= $entry2->created_at ? $entry1 : $entry2;

            if (confirm("Delete newer entry #{$newer->id} (keep older #{$older->id})?", false)) {
                $this->call('know:forget', ['id' => [$newer->id], '--force' => true]);
                $this->info("✅ Deleted duplicate entry #{$newer->id}");
            }
        }
        $this->newLine();
    }

    private function displayMetadataDifferences($entry1, $entry2): void
    {
        $tags1 = $entry1->tagNames ?? [];
        $tags2 = $entry2->tagNames ?? [];

        if ($tags1 !== $tags2) {
            $this->line('         🏷️  Tag differences: '.implode(', ', array_diff($tags1, $tags2)));
        }

        if ($entry1->repo !== $entry2->repo) {
            $this->line("         📂 Different repos: {$entry1->repo} vs {$entry2->repo}");
        }
    }

    private function calculateAdvancedSimilarity(string $str1, string $str2): array
    {
        // 1. Jaccard similarity (word-based)
        $words1 = array_unique(str_word_count(strtolower($str1), 1));
        $words2 = array_unique(str_word_count(strtolower($str2), 1));
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));
        $jaccard = count($union) > 0 ? count($intersection) / count($union) : 0;

        // 2. Levenshtein similarity (character-based)
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            $levenshtein = 1.0;
        } else {
            $distance = levenshtein(substr($str1, 0, 255), substr($str2, 0, 255)); // Limit for performance
            $levenshtein = 1 - ($distance / $maxLen);
        }

        // 3. Semantic similarity (common programming terms, patterns)
        $semantic = $this->calculateSemanticSimilarity($str1, $str2);

        return [
            'jaccard' => max(0, $jaccard),
            'levenshtein' => max(0, $levenshtein),
            'semantic' => max(0, $semantic),
        ];
    }

    private function calculateSemanticSimilarity(string $str1, string $str2): float
    {
        // Common programming and development patterns
        $patterns = [
            '/\b(fix|bug|error|issue)\b/i',
            '/\b(add|implement|create|new)\b/i',
            '/\b(update|modify|change|refactor)\b/i',
            '/\b(test|testing|spec)\b/i',
            '/\b(config|configuration|setup)\b/i',
            '/\b(database|db|migration)\b/i',
            '/\b(api|endpoint|route)\b/i',
            '/\b(component|service|class)\b/i',
            '/\b(performance|optimize|speed)\b/i',
            '/\b(security|auth|permission)\b/i',
        ];

        $matches1 = 0;
        $matches2 = 0;
        $commonMatches = 0;

        foreach ($patterns as $pattern) {
            $match1 = preg_match($pattern, $str1);
            $match2 = preg_match($pattern, $str2);

            $matches1 += $match1;
            $matches2 += $match2;

            if ($match1 && $match2) {
                $commonMatches++;
            }
        }

        $totalMatches = $matches1 + $matches2;

        return $totalMatches > 0 ? (2 * $commonMatches) / $totalMatches : 0;
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Legacy method for backwards compatibility
        $advanced = $this->calculateAdvancedSimilarity($str1, $str2);

        return $advanced['jaccard'];
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
