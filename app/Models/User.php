<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'interests',
        'home_location_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->role ??= 'user';
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'interests' => 'array',
            'password' => 'hashed',
        ];
    }

    public function homeLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'home_location_id');
    }

    public function submittedOpportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'submitted_by');
    }

    public function savedOpportunities(): HasMany
    {
        return $this->hasMany(SavedOpportunity::class);
    }

    public function applicationTrackers(): HasMany
    {
        return $this->hasMany(ApplicationTracker::class, 'user_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(OpportunityReport::class);
    }

    public function verificationRecords(): HasMany
    {
        return $this->hasMany(VerificationRecord::class, 'reviewer_id');
    }

    public function receivedNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'actor_id');
    }
}
