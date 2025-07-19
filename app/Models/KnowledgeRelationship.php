<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeRelationship extends Model
{
    protected $table = 'knowledge_relationships';

    protected $fillable = [
        'from_entry_id',
        'to_entry_id',
        'relationship_type',
        'strength',
        'auto_detected',
    ];

    protected $casts = [
        'from_entry_id' => 'integer',
        'to_entry_id' => 'integer',
        'strength' => 'float',
        'auto_detected' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * From entry relationship
     */
    public function fromEntry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class, 'from_entry_id');
    }

    /**
     * To entry relationship
     */
    public function toEntry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class, 'to_entry_id');
    }

    // ===== SCOPES =====

    /**
     * Filter by relationship type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('relationship_type', $type);
    }

    /**
     * References relationships
     */
    public function scopeReferences(Builder $query): Builder
    {
        return $query->where('relationship_type', 'references');
    }

    /**
     * Similar relationships
     */
    public function scopeSimilar(Builder $query): Builder
    {
        return $query->where('relationship_type', 'similar');
    }

    /**
     * Depends on relationships
     */
    public function scopeDependsOn(Builder $query): Builder
    {
        return $query->where('relationship_type', 'depends_on');
    }

    /**
     * Related to relationships
     */
    public function scopeRelatedTo(Builder $query): Builder
    {
        return $query->where('relationship_type', 'related_to');
    }

    /**
     * Strong relationships (high strength)
     */
    public function scopeStrong(Builder $query, float $threshold = 0.7): Builder
    {
        return $query->where('strength', '>=', $threshold);
    }

    /**
     * Weak relationships (low strength)
     */
    public function scopeWeak(Builder $query, float $threshold = 0.3): Builder
    {
        return $query->where('strength', '<=', $threshold);
    }

    /**
     * Relationships from a specific entry
     */
    public function scopeFromEntry(Builder $query, int $entryId): Builder
    {
        return $query->where('from_entry_id', $entryId);
    }

    /**
     * Relationships to a specific entry
     */
    public function scopeToEntry(Builder $query, int $entryId): Builder
    {
        return $query->where('to_entry_id', $entryId);
    }

    /**
     * Bidirectional relationships involving an entry
     */
    public function scopeInvolvingEntry(Builder $query, int $entryId): Builder
    {
        return $query->where(function (Builder $q) use ($entryId) {
            $q->where('from_entry_id', $entryId)
                ->orWhere('to_entry_id', $entryId);
        });
    }

    /**
     * Order by relationship strength
     */
    public function scopeOrderByStrength(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('strength', $direction);
    }

    /**
     * Recent relationships
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Mutual relationships (both directions exist)
     */
    public function scopeMutual(Builder $query): Builder
    {
        return $query->whereExists(function ($subquery) {
            $subquery->select(\DB::raw(1))
                ->from('knowledge_relationships as kr2')
                ->whereColumn('kr2.from_entry_id', 'knowledge_relationships.to_entry_id')
                ->whereColumn('kr2.to_entry_id', 'knowledge_relationships.from_entry_id');
        });
    }

    /**
     * Create or update relationship with auto-calculated strength
     */
    public static function createOrUpdateRelationship(
        int $fromEntryId,
        int $toEntryId,
        string $type = 'related_to',
        ?float $strength = null
    ): self {
        // Auto-calculate strength if not provided
        if ($strength === null) {
            $strength = static::calculateRelationshipStrength($fromEntryId, $toEntryId);
        }

        return static::updateOrCreate(
            [
                'from_entry_id' => $fromEntryId,
                'to_entry_id' => $toEntryId,
                'relationship_type' => $type,
            ],
            [
                'strength' => $strength,
                'auto_detected' => true,
            ]
        );
    }

    /**
     * Calculate relationship strength based on shared tags, metadata, etc.
     */
    protected static function calculateRelationshipStrength(int $fromEntryId, int $toEntryId): float
    {
        $fromEntry = KnowledgeEntry::with(['tags', 'metadata'])->find($fromEntryId);
        $toEntry = KnowledgeEntry::with(['tags', 'metadata'])->find($toEntryId);

        if (! $fromEntry || ! $toEntry) {
            return 0.0;
        }

        $strength = 0.0;

        // Shared tags contribute to strength
        $fromTags = $fromEntry->tags->pluck('id')->toArray();
        $toTags = $toEntry->tags->pluck('id')->toArray();
        $sharedTags = array_intersect($fromTags, $toTags);
        $totalTags = array_unique(array_merge($fromTags, $toTags));

        if (! empty($totalTags)) {
            $strength += (count($sharedTags) / count($totalTags)) * 0.6; // 60% weight for tags
        }

        // Same repository contributes to strength
        if ($fromEntry->repo && $fromEntry->repo === $toEntry->repo) {
            $strength += 0.2; // 20% weight for same repo
        }

        // Same author contributes to strength
        if ($fromEntry->author && $fromEntry->author === $toEntry->author) {
            $strength += 0.1; // 10% weight for same author
        }

        // Recent creation time proximity
        if ($fromEntry->created_at && $toEntry->created_at) {
            $daysDiff = abs($fromEntry->created_at->diffInDays($toEntry->created_at));
            if ($daysDiff <= 7) {
                $strength += 0.1 * (1 - ($daysDiff / 7)); // Up to 10% for recent proximity
            }
        }

        return min(1.0, $strength); // Cap at 1.0
    }
}
