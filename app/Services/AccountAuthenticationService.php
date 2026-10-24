<?php

namespace App\Services;

use App\Contracts\ContactVerificationPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AccountAuthenticationService
{
    public function __construct(
        private readonly ContactVerificationPolicy $contactVerification,
    ) {}

    public function authenticate(string $identifier, string $password): ?User
    {
        $identifier = trim($identifier);
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $lookup = $isEmail
            ? Str::lower($identifier)
            : (preg_replace('/[\s().-]/', '', $identifier) ?? $identifier);

        $user = User::query()
            ->where($isEmail ? 'email' : 'phone', $lookup)
            ->first();

        $passwordMatches = Hash::check(
            $password,
            $user?->password ?? Hash::make(Str::random(40)),
        );

        if (! $user || ! $passwordMatches || $user->status !== 'active') {
            return null;
        }

        return $user;
    }

    public function isSaveEligible(User $user): bool
    {
        return $user->status === 'active'
            && $this->contactVerification->isSelectedContactVerified($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'home_location_id' => $user->home_location_id,
            'interests' => $user->interests ?? [],
            'contact_verified' => $this->contactVerification->isSelectedContactVerified($user),
            'save_eligible' => $this->isSaveEligible($user),
        ];
    }
}
