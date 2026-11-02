<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Failed sign-in counters for one identifier (email address or phone number).
 *
 * The identifier is normalised before hashing so "User@Example.com " and
 * "user@example.com" share a counter. The plain value is retained only so a
 * support engineer can unlock an account with `auth:unlock`.
 */
class LoginAttempt extends Model
{
    protected $fillable = [
        'identifier_hash',
        'identifier',
        'failed_attempts',
        'last_failed_at',
        'locked_until',
    ];

    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'last_failed_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function retryAfterSeconds(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return max(1, $this->locked_until->getTimestamp() - now()->getTimestamp());
    }
}
