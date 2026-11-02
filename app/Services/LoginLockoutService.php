<?php

namespace App\Services;

use App\Models\LoginAttempt;
use Illuminate\Support\Str;

/**
 * Account lockout for the sign-in endpoints.
 *
 * The rate limiter in front of the route absorbs bursts from one IP. This
 * service answers a different question: how many times has *this account*
 * failed recently, no matter which IP asked? After the configured number of
 * failures the identifier is locked for a fixed window, so a distributed
 * guessing attempt is stopped even when every request comes from a new IP.
 *
 * Counters decay: failures older than the decay window are forgotten, so an
 * ordinary person who mistypes their password twice today is not closer to a
 * lockout next week.
 */
final class LoginLockoutService
{
    /**
     * @return array{locked: bool, retry_after: int, attempts: int}
     */
    public function status(string $identifier): array
    {
        $attempt = $this->find($identifier);

        if ($attempt === null) {
            return ['locked' => false, 'retry_after' => 0, 'attempts' => 0];
        }

        if ($attempt->isLocked()) {
            return [
                'locked' => true,
                'retry_after' => $attempt->retryAfterSeconds(),
                'attempts' => $attempt->failed_attempts,
            ];
        }

        // An expired lock, or a counter that has gone stale, starts over.
        if ($attempt->locked_until !== null || $this->hasDecayed($attempt)) {
            $attempt->delete();

            return ['locked' => false, 'retry_after' => 0, 'attempts' => 0];
        }

        return [
            'locked' => false,
            'retry_after' => 0,
            'attempts' => $attempt->failed_attempts,
        ];
    }

    /**
     * Record one failed sign-in and report whether it crossed the threshold.
     *
     * @return array{locked: bool, retry_after: int, attempts: int}
     */
    public function recordFailure(string $identifier): array
    {
        $normalised = $this->normalise($identifier);
        $attempt = LoginAttempt::query()->firstOrNew([
            'identifier_hash' => $this->hash($normalised),
        ]);

        if ($attempt->exists && $attempt->isLocked()) {
            return [
                'locked' => true,
                'retry_after' => $attempt->retryAfterSeconds(),
                'attempts' => $attempt->failed_attempts,
            ];
        }

        if (! $attempt->exists || $this->hasDecayed($attempt) || $attempt->locked_until !== null) {
            $attempt->failed_attempts = 0;
            $attempt->locked_until = null;
        }

        $attempt->identifier = $normalised;
        $attempt->failed_attempts = (int) $attempt->failed_attempts + 1;
        $attempt->last_failed_at = now();

        if ($attempt->failed_attempts >= $this->maxAttempts()) {
            $attempt->locked_until = now()->addMinutes($this->lockoutMinutes());
        }

        $attempt->save();

        return [
            'locked' => $attempt->isLocked(),
            'retry_after' => $attempt->retryAfterSeconds(),
            'attempts' => $attempt->failed_attempts,
        ];
    }

    /**
     * Forget every failure for this identifier. Called after a real sign-in.
     */
    public function clear(string $identifier): void
    {
        LoginAttempt::query()
            ->where('identifier_hash', $this->hash($this->normalise($identifier)))
            ->delete();
    }

    private function find(string $identifier): ?LoginAttempt
    {
        return LoginAttempt::query()
            ->where('identifier_hash', $this->hash($this->normalise($identifier)))
            ->first();
    }

    private function hasDecayed(LoginAttempt $attempt): bool
    {
        return $attempt->last_failed_at === null
            || $attempt->last_failed_at->lt(now()->subMinutes($this->decayMinutes()));
    }

    private function normalise(string $identifier): string
    {
        $identifier = trim($identifier);

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            return Str::lower($identifier);
        }

        return preg_replace('/[\s().-]/', '', $identifier) ?? $identifier;
    }

    private function hash(string $identifier): string
    {
        return hash('sha256', 'login-lockout|'.$identifier);
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('oyafind.auth.max_attempts', 5));
    }

    private function decayMinutes(): int
    {
        return max(1, (int) config('oyafind.auth.decay_minutes', 15));
    }

    private function lockoutMinutes(): int
    {
        return max(1, (int) config('oyafind.auth.lockout_minutes', 15));
    }
}
