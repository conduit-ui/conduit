<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class KnowledgeService
{
    /**
     * Add a new knowledge entry with tags and metadata.
     */
    public function addEntry(string $content, array $tags = [], array $metadata = []): int
    {
        return DB::transaction(function () use ($content, $tags, $metadata) {
            // Get git context
            $gitContext = $this->getGitContext();

            // Create main entry
            $entryId = DB::table('knowledge_entries')->insertGetId([
                'content' => $content,
                'repo' => $gitContext['repo'],
                'branch' => $gitContext['branch'],
                'commit_sha' => $gitContext['commit_sha'],
                'author' => $gitContext['author'],
                'project_type' => $gitContext['project_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add tags
            if (! empty($tags)) {
                $this->addTagsToEntry($entryId, $tags);
            }

            // Add metadata (priority, status, etc.)
            foreach ($metadata as $key => $value) {
                $this->addMetadataToEntry($entryId, $key, $value);
            }

            return $entryId;
        });
    }

    /**
     * Search knowledge entries with advanced filtering.
     */
    public function searchEntries(string $query = '', array $filters = []): Collection
    {
        $dbQuery = DB::table('knowledge_entries as ke');

        // Basic content search
        if (! empty($query)) {
            $dbQuery->where(function ($q) use ($query) {
                $q->where('ke.content', 'LIKE', "%{$query}%");

                // Also search in tags
                $q->orWhereExists(function ($subquery) use ($query) {
                    $subquery->select(DB::raw(1))
                        ->from('knowledge_entry_tags as ket')
                        ->join('knowledge_tags as kt', 'ket.tag_id', '=', 'kt.id')
                        ->whereColumn('ket.entry_id', 'ke.id')
                        ->where('kt.name', 'LIKE', "%{$query}%");
                });
            });
        }

        // Apply filters
        if (! empty($filters['repo'])) {
            $dbQuery->where('ke.repo', 'LIKE', '%'.$filters['repo'].'%');
        }

        if (! empty($filters['branch'])) {
            $dbQuery->where('ke.branch', $filters['branch']);
        }

        if (! empty($filters['author'])) {
            $dbQuery->where('ke.author', 'LIKE', '%'.$filters['author'].'%');
        }

        if (! empty($filters['type'])) {
            $dbQuery->where('ke.project_type', $filters['type']);
        }

        // Tag filtering
        if (! empty($filters['tags'])) {
            $tagNames = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
            foreach ($tagNames as $tag) {
                $dbQuery->whereExists(function ($subquery) use ($tag) {
                    $subquery->select(DB::raw(1))
                        ->from('knowledge_entry_tags as ket')
                        ->join('knowledge_tags as kt', 'ket.tag_id', '=', 'kt.id')
                        ->whereColumn('ket.entry_id', 'ke.id')
                        ->where('kt.name', 'LIKE', '%'.trim($tag).'%');
                });
            }
        }

        // TODO filtering
        if (! empty($filters['todo'])) {
            $dbQuery->whereExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('knowledge_entry_tags as ket')
                    ->join('knowledge_tags as kt', 'ket.tag_id', '=', 'kt.id')
                    ->whereColumn('ket.entry_id', 'ke.id')
                    ->where('kt.name', 'todo');
            });
        }

        // Metadata filtering (priority, status)
        foreach (['priority', 'status'] as $metaKey) {
            if (! empty($filters[$metaKey])) {
                $dbQuery->whereExists(function ($subquery) use ($metaKey, $filters) {
                    $subquery->select(DB::raw(1))
                        ->from('knowledge_metadata as km')
                        ->whereColumn('km.entry_id', 'ke.id')
                        ->where('km.key', $metaKey)
                        ->where('km.value', $filters[$metaKey]);
                });
            }
        }

        // Recent filter (last 7 days)
        if (! empty($filters['recent'])) {
            $dbQuery->where('ke.created_at', '>=', now()->subDays(7));
        }

        // Context mode: prefer current repository
        if (! empty($filters['context'])) {
            $gitContext = $this->getGitContext();
            if ($gitContext['repo']) {
                $dbQuery->orderByRaw('CASE WHEN ke.repo = ? THEN 0 ELSE 1 END', [$gitContext['repo']]);
            }
        }

        $limit = $filters['limit'] ?? 10;

        return $dbQuery->orderBy('ke.created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get a specific knowledge entry with full details.
     */
    public function getEntry(int $id): ?object
    {
        $entry = DB::table('knowledge_entries')->where('id', $id)->first();

        if (! $entry) {
            return null;
        }

        // Enhance with tags and metadata
        $entry->tags = $this->getEntryTags($id);
        $entry->metadata = $this->getEntryMetadata($id);
        $entry->priority = $this->getMetadataValue($id, 'priority', 'medium');
        $entry->status = $this->getMetadataValue($id, 'status', 'open');

        return $entry;
    }

    /**
     * Delete a knowledge entry and all related data.
     */
    public function deleteEntry(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            // Delete tag relationships
            DB::table('knowledge_entry_tags')->where('entry_id', $id)->delete();

            // Delete metadata
            DB::table('knowledge_metadata')->where('entry_id', $id)->delete();

            // Delete relationships
            DB::table('knowledge_relationships')
                ->where('from_entry_id', $id)
                ->orWhere('to_entry_id', $id)
                ->delete();

            // Delete main entry
            return DB::table('knowledge_entries')->where('id', $id)->delete() > 0;
        });
    }

    /**
     * Get all tags for a knowledge entry.
     */
    public function getEntryTags(int $entryId): array
    {
        return DB::table('knowledge_entry_tags as ket')
            ->join('knowledge_tags as kt', 'ket.tag_id', '=', 'kt.id')
            ->where('ket.entry_id', $entryId)
            ->pluck('kt.name')
            ->toArray();
    }

    /**
     * Get all metadata for a knowledge entry.
     */
    public function getEntryMetadata(int $entryId): array
    {
        return DB::table('knowledge_metadata')
            ->where('entry_id', $entryId)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Get specific metadata value.
     */
    public function getMetadataValue(int $entryId, string $key, $default = null)
    {
        return DB::table('knowledge_metadata')
            ->where('entry_id', $entryId)
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    /**
     * Add tags to an entry.
     */
    public function addTagsToEntry(int $entryId, array $tags): void
    {
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            // Get or create tag
            $tagId = DB::table('knowledge_tags')->where('name', $tagName)->value('id');

            if (! $tagId) {
                $tagId = DB::table('knowledge_tags')->insertGetId([
                    'name' => $tagName,
                    'usage_count' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Increment usage count
                DB::table('knowledge_tags')->where('id', $tagId)->increment('usage_count');
            }

            // Link entry to tag (avoid duplicates)
            DB::table('knowledge_entry_tags')->insertOrIgnore([
                'entry_id' => $entryId,
                'tag_id' => $tagId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Add metadata to an entry.
     */
    public function addMetadataToEntry(int $entryId, string $key, string $value, string $type = 'string'): void
    {
        DB::table('knowledge_metadata')->updateOrInsert(
            ['entry_id' => $entryId, 'key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Get current git context.
     */
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
            $context['project_type'] = $this->detectProjectType();

        } catch (\Exception $e) {
            // Git context is optional
        }

        return $context;
    }

    /**
     * Run a git command safely.
     */
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

    /**
     * Detect project type.
     */
    private function detectProjectType(): ?string
    {
        if (file_exists('composer.json')) {
            $composer = json_decode(file_get_contents('composer.json'), true);

            if (isset($composer['require']['laravel-zero/framework'])) {
                return 'laravel-zero';
            }

            if (isset($composer['require']['laravel/framework'])) {
                return 'laravel';
            }

            return 'php';
        }

        if (file_exists('package.json')) {
            return 'node';
        }

        return null;
    }

    /**
     * Get most popular tags.
     */
    public function getPopularTags(int $limit = 20): Collection
    {
        return DB::table('knowledge_tags')
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get entry count by repository.
     */
    public function getRepositoryStats(): Collection
    {
        return DB::table('knowledge_entries')
            ->select('repo', DB::raw('COUNT(*) as count'))
            ->groupBy('repo')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * Search for related entries based on tags and content similarity.
     */
    public function getRelatedEntries(int $entryId, int $limit = 5): Collection
    {
        $entry = $this->getEntry($entryId);
        if (! $entry) {
            return collect();
        }

        // Find entries with similar tags
        $query = DB::table('knowledge_entries as ke')
            ->where('ke.id', '!=', $entryId);

        if (! empty($entry->tags)) {
            $query->whereExists(function ($subquery) use ($entry) {
                $subquery->select(DB::raw(1))
                    ->from('knowledge_entry_tags as ket')
                    ->join('knowledge_tags as kt', 'ket.tag_id', '=', 'kt.id')
                    ->whereColumn('ket.entry_id', 'ke.id')
                    ->whereIn('kt.name', $entry->tags);
            });
        }

        return KnowledgeEntry::withDetails()
            ->relatedTo($entry)
            ->limit($limit)
            ->get();
    }

    /**
     * Attach tags to an entry with proper tag management.
     */
    private function attachTagsToEntry(KnowledgeEntry $entry, array $tags): void
    {
        $tagIds = [];

        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            $tag = KnowledgeTag::findOrCreateByName($tagName);
            $tagIds[] = $tag->id;
        }

        // Sync tags (this will add new ones and keep existing)
        $entry->tags()->syncWithoutDetaching($tagIds);

        // Update usage counts for newly attached tags
        foreach ($tagIds as $tagId) {
            $tag = KnowledgeTag::find($tagId);
            if ($tag && ! $entry->tags()->where('id', $tagId)->exists()) {
                $tag->incrementUsage();
            }
        }
    }
}
