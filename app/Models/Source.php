<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reviewed, machine-readable place that OyaFind may pull candidate
 * opportunities from. A source is never public on its own; every item it
 * yields must pass review before it can become an opportunity.
 */
class Source extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'homepage_url',
        'feed_url',
        'adapter',
        'default_category_slug',
        'timezone',
        'attribution',
        'terms_url',
        'enabled',
        'last_fetched_at',
        'last_status',
        'last_error',
        'last_item_count',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_fetched_at' => 'immutable_datetime',
            'last_item_count' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SourceItem::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * The host that every item link must belong to. Prevents a compromised or
     * misconfigured feed from publishing links to an unrelated domain.
     */
    public function baseHost(): ?string
    {
        $host = parse_url((string) $this->homepage_url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower(preg_replace('/^www\./', '', $host));
    }
}
