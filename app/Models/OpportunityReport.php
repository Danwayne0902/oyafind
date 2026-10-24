<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityReport extends Model
{
    protected $fillable = [
        'opportunity_id',
        'user_id',
        'reason',
        'details',
        'status',
        'resolved_by',
        'resolution_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
