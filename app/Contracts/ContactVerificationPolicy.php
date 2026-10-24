<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Exposes the verification state of a user's selected registration contact.
 *
 * This contract deliberately contains no delivery or verification operation.
 */
interface ContactVerificationPolicy
{
    public function isSelectedContactVerified(User $user): bool;
}
