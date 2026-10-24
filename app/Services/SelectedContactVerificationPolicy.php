<?php

namespace App\Services;

use App\Contracts\ContactVerificationPolicy;
use App\Models\User;

final class SelectedContactVerificationPolicy implements ContactVerificationPolicy
{
    public function isSelectedContactVerified(User $user): bool
    {
        $hasEmail = filled($user->email);
        $hasPhone = filled($user->phone);

        // The schema does not record which contact was selected when both exist.
        // Fail closed rather than treating verification of either as sufficient.
        if ($hasEmail === $hasPhone) {
            return false;
        }

        return $hasEmail
            ? $user->email_verified_at !== null
            : $user->phone_verified_at !== null;
    }
}
