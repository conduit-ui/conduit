<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeTag extends Model
{
    protected $table = 'knowledge_tags';

    protected $fillable = [
        'name',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Entries relationship
     */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeEntry::class,
            'knowledge_entry_tags',
            'tag_id',
            'entry_id'
        )->withTimestamps();
    }

    /**
     * Increment usage count when tag is used
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Decrement usage count when tag is removed
     */
    public function decrementUsage(): void
    {
        $this->decrement('usage_count');
    }

    // ===== SCOPES =====

    /**
     * Most popular tags
     */
    public function scopePopular(Builder $query, int $limit = 20): Builder
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    /**
     * Recently used tags
     */
    public function scopeRecentlyUsed(Builder $query, int $days = 30): Builder
    {
        return $query->whereHas('entries', function (Builder $entryQuery) use ($days) {
            $entryQuery->where('knowledge_entries.created_at', '>=', now()->subDays($days));
        })->orderBy('updated_at', 'desc');
    }

    /**
     * Find or create tag by name
     */
    public static function findOrCreateByName(string $name): self
    {
        return static::firstOrCreate(
            ['name' => trim($name)],
            ['usage_count' => 0]
        );
    }

    /**
     * Get usage statistics
     */
    public function scopeWithUsageStats(Builder $query): Builder
    {
        return $query->withCount('entries')
            ->orderBy('entries_count', 'desc');
    }

    /**
     * Search tags by name
     */
    public function scopeSearchByName(Builder $query, string $search): Builder
    {
        return $query->where('name', 'LIKE', "%{$search}%");
    }

    /**
     * Unused tags (0 usage count)
     */
    public function scopeUnused(Builder $query): Builder
    {
        return $query->where('usage_count', 0);
    }

    /**
     * Tags used in specific repository
     */
    public function scopeUsedInRepo(Builder $query, string $repo): Builder
    {
        return $query->whereHas('entries', function (Builder $entryQuery) use ($repo) {
            $entryQuery->where('repo', 'LIKE', "%{$repo}%");
        });
    }
}
