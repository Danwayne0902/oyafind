<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Opportunity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'category_id',
        'location_id',
        'submitted_by',
        'organisation_name',
        'source_url',
        'application_url',
        'application_method',
        'application_instructions',
        'opportunity_type',
        'work_mode',
        'eligibility',
        'requirements',
        'amount_min',
        'amount_max',
        'currency',
        'deadline_at',
        'status',
        'verification_status',
        'published_at',
        'verified_at',
        'verified_by',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'amount_min' => 'decimal:2',
            'amount_max' => 'decimal:2',
            'deadline_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'view_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Opportunity $opportunity): void {
            if ($opportunity->amount_min !== null && (float) $opportunity->amount_min < 0) {
                throw new InvalidArgumentException('The minimum amount cannot be negative.');
            }

            if ($opportunity->amount_max !== null && (float) $opportunity->amount_max < 0) {
                throw new InvalidArgumentException('The maximum amount cannot be negative.');
            }

            if ($opportunity->amount_min !== null
                && $opportunity->amount_max !== null
                && (float) $opportunity->amount_max < (float) $opportunity->amount_min) {
                throw new InvalidArgumentException('The maximum amount cannot be below the minimum amount.');
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function savedBy(): HasMany
    {
        return $this->hasMany(SavedOpportunity::class);
    }

    public function applicationTrackers(): HasMany
    {
        return $this->hasMany(ApplicationTracker::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(OpportunityReport::class);
    }

    public function verificationRecords(): HasMany
    {
        return $this->hasMany(VerificationRecord::class);
    }

    /**
     * The most recent verified check on record. Public listing eligibility
     * already requires a current qualifying check; this relation supplies the
     * public check date without trusting the legacy verified_at column.
     */
    public function latestVerifiedCheck(): HasOne
    {
        return $this->hasOne(VerificationRecord::class)->ofMany(
            ['checked_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('result', 'verified'),
        );
    }
}
