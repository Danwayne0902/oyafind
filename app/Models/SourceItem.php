<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A candidate listing exactly as it was found at a source. Items are staging
 * records: they are not public, not searchable and not verifiable evidence
 * until a reviewer approves them.
 */
class SourceItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FILTERED = 'filtered';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'source_id',
        'external_id',
        'url_hash',
        'canonical_url',
        'title',
        'organisation_name',
        'summary',
        'excerpt',
        'published_at',
        'deadline_at',
        'location_text',
        'location_id',
        'category_slug',
        'status',
        'status_reason',
        'dedupe_key',
        'fetched_at',
        'reviewed_at',
        'reviewed_by',
        'opportunity_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
            'fetched_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
