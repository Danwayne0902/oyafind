<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class VerificationRecord extends Model
{
    protected $table = 'opportunity_verifications';

    public $timestamps = false;

    protected $fillable = [
        'opportunity_id',
        'reviewer_id',
        'result',
        'check_type',
        'evidence_url',
        'evidence_summary',
        'evidence_rationale',
        'restricted_notes',
        'checked_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (VerificationRecord $record): void {
            if (blank($record->evidence_url) && blank($record->evidence_rationale)) {
                throw new InvalidArgumentException(
                    'A verification record requires an evidence reference or documented rationale.'
                );
            }
        });

        static::updating(fn () => throw new LogicException('Verification records are append-only.'));
        static::deleting(fn () => throw new LogicException('Verification records are append-only.'));
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
