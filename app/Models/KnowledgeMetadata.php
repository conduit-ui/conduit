<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeMetadata extends Model
{
    protected $table = 'knowledge_metadata';

    protected $fillable = [
        'entry_id',
        'key',
        'value',
        'type',
    ];

    protected $casts = [
        'entry_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Entry relationship
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class, 'entry_id');
    }

    /**
     * Get typed value based on type column
     */
    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value, // string
        };
    }

    /**
     * Set typed value and auto-detect type
     */
    public function setTypedValue($value): void
    {
        if (is_int($value)) {
            $this->type = 'integer';
            $this->value = (string) $value;
        } elseif (is_float($value)) {
            $this->type = 'float';
            $this->value = (string) $value;
        } elseif (is_bool($value)) {
            $this->type = 'boolean';
            $this->value = $value ? '1' : '0';
        } elseif (is_array($value) || is_object($value)) {
            $this->type = 'json';
            $this->value = json_encode($value);
        } else {
            $this->type = 'string';
            $this->value = (string) $value;
        }
    }

    // ===== SCOPES =====

    /**
     * Filter by key
     */
    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /**
     * Filter by value
     */
    public function scopeByValue(Builder $query, string $value): Builder
    {
        return $query->where('value', $value);
    }

    /**
     * Filter by type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Priority metadata only
     */
    public function scopePriority(Builder $query): Builder
    {
        return $query->where('key', 'priority');
    }

    /**
     * Status metadata only
     */
    public function scopeStatus(Builder $query): Builder
    {
        return $query->where('key', 'status');
    }

    /**
     * Get unique metadata keys
     */
    public function scopeUniqueKeys(Builder $query): Builder
    {
        return $query->select('key')->distinct()->orderBy('key');
    }

    /**
     * Get metadata statistics
     */
    public function scopeKeyUsageStats(Builder $query): Builder
    {
        return $query->selectRaw('key, COUNT(*) as usage_count')
            ->groupBy('key')
            ->orderBy('usage_count', 'desc');
    }

    /**
     * Search metadata values
     */
    public function scopeSearchValue(Builder $query, string $search): Builder
    {
        return $query->where('value', 'LIKE', "%{$search}%");
    }

    /**
     * Recent metadata changes
     */
    public function scopeRecentChanges(Builder $query, int $days = 7): Builder
    {
        return $query->where('updated_at', '>=', now()->subDays($days))
            ->orderBy('updated_at', 'desc');
    }
}
