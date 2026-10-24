<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Location extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'location_type',
        'is_active',
        'reviewed_at',
        'reviewed_by',
        'catalogue_origin',
        'source_name',
        'source_reference',
        'source_version',
        'source_retrieved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reviewed_at' => 'datetime',
            'source_retrieved_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Location $location): void {
            if ($location->is_active && (
                ! $location->reviewed_at
                || ! $location->reviewed_by
                || blank($location->catalogue_origin)
            )) {
                throw new InvalidArgumentException(
                    'A selectable location requires a recorded origin, reviewer, and review time.'
                );
            }

            $hasProvenance = filled($location->source_name)
                || filled($location->source_reference)
                || filled($location->source_version)
                || filled($location->source_retrieved_at);
            $isExternalOrigin = filled($location->catalogue_origin)
                && $location->catalogue_origin !== 'oyafind_reviewed';

            if ($hasProvenance || $isExternalOrigin) {
                foreach (['source_reference', 'source_version', 'source_retrieved_at'] as $field) {
                    if (blank($location->{$field})) {
                        throw new InvalidArgumentException(
                            'Source-derived locations require complete provenance.'
                        );
                    }
                }

                if (blank($location->source_name)) {
                    throw new InvalidArgumentException(
                        'Source-derived locations require a source name.'
                    );
                }
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }
}
