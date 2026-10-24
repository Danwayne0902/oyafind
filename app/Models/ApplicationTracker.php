<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationTracker extends Model
{
    protected $table = 'user_applications';

    protected $fillable = [
        'user_id',
        'opportunity_id',
        'status',
        'status_source',
        'notes',
        'applied_at',
    ];

    protected function casts(): array
    {
        return ['applied_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
