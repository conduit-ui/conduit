<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeEntry extends Model
{
    protected $table = 'knowledge_entries';

    protected $fillable = [
        'content',
        'repo',
        'branch',
        'commit_sha',
        'author',
        'project_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tags relationship
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeTag::class,
            'knowledge_entry_tags',
            'entry_id',
            'tag_id'
        )->withTimestamps();
    }

    /**
     * Metadata relationship
     */
    public function metadata(): HasMany
    {
        return $this->hasMany(KnowledgeMetadata::class, 'entry_id');
    }

    /**
     * From relationships
     */
    public function fromRelationships(): HasMany
    {
        return $this->hasMany(KnowledgeRelationship::class, 'from_entry_id');
    }

    /**
     * To relationships
     */
    public function toRelationships(): HasMany
    {
        return $this->hasMany(KnowledgeRelationship::class, 'to_entry_id');
    }

    /**
     * Get priority from metadata
     */
    public function getPriorityAttribute(): string
    {
        return $this->getMetadataValue('priority', 'medium');
    }

    /**
     * Get status from metadata
     */
    public function getStatusAttribute(): string
    {
        return $this->getMetadataValue('status', 'open');
    }

    /**
     * Check if entry is a TODO
     */
    public function getIsTodoAttribute(): bool
    {
        return $this->tags->contains('name', 'todo');
    }

    /**
     * Get tag names as array
     */
    public function getTagNamesAttribute(): array
    {
        return $this->tags->pluck('name')->toArray();
    }

    /**
     * Helper to get metadata value
     */
    public function getMetadataValue(string $key, $default = null)
    {
        $metadata = $this->metadata->where('key', $key)->first();

        return $metadata ? $metadata->value : $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadataValue(string $key, string $value, string $type = 'string'): void
    {
        $this->metadata()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    // ===== SCOPES =====

    /**
     * Search content and tags
     */
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->where(function (Builder $q) use ($searchTerm) {
            $q->where('content', 'LIKE', "%{$searchTerm}%")
                ->orWhereHas('tags', function (Builder $tagQuery) use ($searchTerm) {
                    $tagQuery->where('name', 'LIKE', "%{$searchTerm}%");
                });
        });
    }

    /**
     * Filter by repository
     */
    public function scopeByRepo(Builder $query, string $repo): Builder
    {
        return $query->where('repo', 'LIKE', "%{$repo}%");
    }

    /**
     * Filter by branch
     */
    public function scopeByBranch(Builder $query, string $branch): Builder
    {
        return $query->where('branch', $branch);
    }

    /**
     * Filter by author
     */
    public function scopeByAuthor(Builder $query, string $author): Builder
    {
        return $query->where('author', 'LIKE', "%{$author}%");
    }

    /**
     * Filter by project type
     */
    public function scopeByProjectType(Builder $query, string $type): Builder
    {
        return $query->where('project_type', $type);
    }

    /**
     * Filter by tags
     */
    public function scopeWithTags(Builder $query, array $tags): Builder
    {
        foreach ($tags as $tag) {
            $query->whereHas('tags', function (Builder $tagQuery) use ($tag) {
                $tagQuery->where('name', 'LIKE', "%{$tag}%");
            });
        }

        return $query;
    }

    /**
     * Filter to only TODO items
     */
    public function scopeTodoOnly(Builder $query): Builder
    {
        return $query->whereHas('tags', function (Builder $tagQuery) {
            $tagQuery->where('name', 'todo');
        });
    }

    /**
     * Filter by priority
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->whereHas('metadata', function (Builder $metaQuery) use ($priority) {
            $metaQuery->where('key', 'priority')->where('value', $priority);
        });
    }

    /**
     * Filter by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('metadata', function (Builder $metaQuery) use ($status) {
            $metaQuery->where('key', 'status')->where('value', $status);
        });
    }

    /**
     * Recent entries (last N days)
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Order by relevance for current repo
     */
    public function scopeOrderByRelevance(Builder $query, ?string $currentRepo = null): Builder
    {
        if ($currentRepo) {
            return $query->orderByRaw('CASE WHEN repo = ? THEN 0 ELSE 1 END', [$currentRepo])
                ->orderBy('created_at', 'desc');
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * With full details (tags and metadata)
     */
    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with(['tags', 'metadata']);
    }

    /**
     * Find related entries based on shared tags
     */
    public function scopeRelatedTo(Builder $query, KnowledgeEntry $entry): Builder
    {
        $tagIds = $entry->tags->pluck('id')->toArray();

        if (empty($tagIds)) {
            return $query->whereRaw('1 = 0'); // No results if no tags
        }

        return $query->where('knowledge_entries.id', '!=', $entry->id)
            ->whereHas('tags', function (Builder $tagQuery) use ($tagIds) {
                $tagQuery->whereIn('knowledge_tags.id', $tagIds);
            })
            ->withCount(['tags' => function (Builder $tagQuery) use ($tagIds) {
                $tagQuery->whereIn('knowledge_tags.id', $tagIds);
            }])
            ->orderBy('tags_count', 'desc');
    }
}
