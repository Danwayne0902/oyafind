<?php

namespace App\Console\Commands;

use App\Models\LoginAttempt;
use Illuminate\Console\Command;

/**
 * Support command for the sign-in lockout. A person who has locked themselves
 * out can be cleared immediately instead of waiting for the window to pass.
 * The identifier is matched the same way the lockout matches it, so an email
 * address is case-insensitive and a phone number ignores separators.
 */
class UnlockAccountCommand extends Command
{
    protected $signature = 'auth:unlock {identifier : Email address or phone number}';

    protected $description = 'Clear failed sign-in counters and any lockout for one account';

    public function handle(): int
    {
        $identifier = trim((string) $this->argument('identifier'));
        $normalised = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false
            ? mb_strtolower($identifier)
            : (preg_replace('/[\s().-]/', '', $identifier) ?? $identifier);

        $deleted = LoginAttempt::query()
            ->where('identifier_hash', hash('sha256', 'login-lockout|'.$normalised))
            ->delete();

        if ($deleted === 0) {
            $this->info('No lockout or failed-attempt record exists for that identifier.');

            return self::SUCCESS;
        }

        $this->info('Cleared the sign-in lockout for '.$normalised.'.');

        return self::SUCCESS;
    }
}
