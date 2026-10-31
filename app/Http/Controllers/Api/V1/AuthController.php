<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AccountAuthenticationService;
use App\Services\AccountRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function csrfToken(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => $request->session()->token()]);
    }

    public function registrationStatus(AccountRegistrationService $registration): JsonResponse
    {
        $available = $registration->isAvailable();
        $mode = $available ? $registration->registrationMode() : null;

        return response()->json([
            'data' => [
                'available' => $available,
                'message' => $available
                    ? ($mode === AccountRegistrationService::MODE_DIRECT
                        ? 'You can sign up with an email address or phone number. Your contact stays unverified for now, so saving opportunities is not yet available.'
                        : null)
                    : $registration->unavailableMessage(),
                'mode' => $mode,
            ],
        ]);
    }

    public function register(
        RegisterRequest $request,
        AccountRegistrationService $registration,
    ): JsonResponse {
        if (! $registration->isAvailable()) {
            return response()->json([
                'message' => $registration->unavailableMessage(),
            ], 503);
        }

        if ($registration->registrationMode() === AccountRegistrationService::MODE_DIRECT) {
            $user = $registration->registerDirectly(
                $request->validated('name'),
                $request->validated('contact'),
                $request->validated('password'),
            );

            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return response()->json([
                'message' => 'Your OyaFind account is ready. Your contact is not verified yet, so saving opportunities stays unavailable until verification is enabled.',
                'data' => app(AccountAuthenticationService::class)->profile($user),
                'registration_mode' => AccountRegistrationService::MODE_DIRECT,
            ], 201);
        }

        $contact = $request->validated('contact');
        if (filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
            return response()->json([
                'message' => 'Email verification is required for this registration mode. Enter an email address.',
                'errors' => ['contact' => ['Enter an email address to receive a verification link.']],
            ], 422);
        }

        if (! $registration->register(
            $request->validated('name'),
            $contact,
            $request->validated('password'),
        )) {
            return response()->json([
                'message' => 'We could not send the verification message. Please try again in a moment.',
            ], 503);
        }

        return response()->json([
            'message' => 'Check your inbox for a verification link, then sign in.',
        ], 202);
    }

    public function verificationLink(Request $request, User $user, string $hash)
    {
        return redirect()->route('email-verification.page', [
            'user' => $user->getKey(),
            'hash' => $hash,
            'expires' => $request->query('expires'),
            'signature' => $request->query('signature'),
        ]);
    }

    public function verifyEmail(User $user, string $hash): JsonResponse
    {
        if (! $user->email || ! hash_equals(sha1($user->email), $hash)) {
            return response()->json(['message' => 'This verification link is invalid.'], 422);
        }

        if ($user->status === 'active' && $user->email_verified_at !== null) {
            return response()->json(['message' => 'Your email is already verified.']);
        }

        if ($user->status !== 'pending' || $user->email_verified_at !== null) {
            return response()->json([
                'message' => 'This account cannot be activated with this link.',
            ], 409);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'status' => 'active',
        ])->save();

        return response()->json([
            'message' => 'Your email is verified. You can now sign in to OyaFind.',
        ]);
    }

    public function login(
        LoginRequest $request,
        AccountAuthenticationService $authentication,
    ): JsonResponse {
        // The route-level throttle stops one client hammering the endpoint.
        // This second limit is keyed by the identifier itself, so a distributed
        // attempt against one account is slowed without locking out everyone
        // who shares an office or mobile network IP.
        $throttleKey = $this->loginThrottleKey($request);
        $maxAttempts = 5;

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);

            return response()
                ->json([
                    'message' => 'Too many sign-in attempts for this account. Try again in '.max(1, (int) ceil($retryAfter / 60)).' minute(s).',
                ], 429)
                ->header('Retry-After', (string) $retryAfter);
        }

        $user = $authentication->authenticate(
            $request->validated('identifier'),
            $request->validated('password'),
        );

        if (! $user) {
            RateLimiter::hit($throttleKey, 900);

            return response()->json([
                'message' => 'The provided credentials are invalid or the account is unavailable.',
            ], 422);
        }

        RateLimiter::clear($throttleKey);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'data' => $authentication->profile($user),
        ]);
    }

    private function loginThrottleKey(Request $request): string
    {
        $identifier = Str::lower(trim((string) $request->input('identifier')));

        return 'login-attempt:'.hash('sha256', $identifier).'|'.$request->ip();
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'You have been signed out.']);
    }
}
