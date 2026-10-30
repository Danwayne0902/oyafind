<?php

namespace App\Services;

use App\Mail\VerifyAccountEmail;
use App\Models\User;
use App\Support\PolicyConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class AccountRegistrationService
{
    public const MODE_DIRECT = 'direct';

    public const MODE_EMAIL_VERIFICATION = 'email_verification';

    public function __construct(
        private readonly PolicyConfiguration $policies,
    ) {}

    public function isAvailable(): bool
    {
        if (! $this->registrationAllowed()) {
            return false;
        }

        if ($this->registrationMode() === self::MODE_DIRECT) {
            return true;
        }

        return $this->emailDeliveryIsConfigured();
    }

    /**
     * Registration mode is an explicit, owner-approved decision. When the
     * verification mailer is not set up (or the operator explicitly chooses
     * it) accounts are created active with an unverified contact.
     */
    public function registrationMode(): string
    {
        $configured = config('oyafind.registration.mode');

        if (is_string($configured) && in_array($configured, [self::MODE_DIRECT, self::MODE_EMAIL_VERIFICATION], true)) {
            return $configured;
        }

        if ($this->legacyLocalBypassEnabled()) {
            return self::MODE_DIRECT;
        }

        return self::MODE_EMAIL_VERIFICATION;
    }

    public function unavailableMessage(): string
    {
        if (! $this->registrationAllowed()) {
            return 'Registration is currently closed. Please check back shortly.';
        }

        return 'Registration is temporarily unavailable because the verification email service is not configured. Please try again later.';
    }

    /**
     * Create an account that can sign in immediately. The contact stays
     * unverified, so actions that require a verified contact remain locked.
     */
    public function registerDirectly(string $name, string $contact, string $password): User
    {
        if (! $this->registrationAllowed()) {
            throw new \LogicException('Registration is not enabled.');
        }

        $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
        $attributes = $isEmail
            ? ['email' => Str::lower(trim($contact)), 'phone' => null]
            : ['email' => null, 'phone' => $contact];

        $user = new User([
            'name' => trim($name),
            'password' => $password,
            ...$attributes,
        ]);
        $user->forceFill(['status' => 'active'])->save();

        return $user->fresh();
    }

    /**
     * Create or resume an account gated behind email verification.
     */
    public function register(string $name, string $email, string $password): bool
    {
        $email = Str::lower(trim($email));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user) {
            if ($user->status === 'pending' && $user->email_verified_at === null) {
                return $this->sendVerification($user);
            }

            return true;
        }

        return DB::transaction(function () use ($name, $email, $password): bool {
            $user = new User([
                'name' => trim($name),
                'email' => $email,
                'password' => $password,
            ]);
            $user->forceFill(['status' => 'pending'])->save();

            if ($this->sendVerification($user->fresh())) {
                return true;
            }

            $user->delete();

            return false;
        });
    }

    private function sendVerification(User $user): bool
    {
        try {
            $verificationUrl = URL::temporarySignedRoute(
                'api.v1.auth.verify-email',
                now()->addMinutes((int) config('oyafind.email_verification.link_expiration_minutes')),
                [
                    'user' => $user->getKey(),
                    'hash' => sha1($user->email),
                ],
            );

            $expirationMinutes = (int) config('oyafind.email_verification.link_expiration_minutes');
            Mail::to($user->email)->send(new VerifyAccountEmail($user, $verificationUrl, $expirationMinutes));
        } catch (TransportExceptionInterface $exception) {
            Log::error('OyaFind verification email delivery failed.', [
                'user_id' => $user->getKey(),
                'exception_type' => $exception::class,
            ]);

            return false;
        }

        return true;
    }

    private function emailDeliveryIsConfigured(): bool
    {
        $mailer = config('mail.default');
        $fromAddress = (string) config('mail.from.address');
        $applicationUrl = (string) config('app.url');
        $applicationHost = parse_url($applicationUrl, PHP_URL_HOST);
        $signingKey = config('app.key');
        $expirationMinutes = filter_var(
            config('oyafind.email_verification.link_expiration_minutes'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        $applicationUrlIsAddressable = filter_var($applicationUrl, FILTER_VALIDATE_URL) !== false
            && is_string($applicationHost)
            && ! in_array(strtolower($applicationHost), ['localhost', '127.0.0.1', '::1'], true);

        if (! in_array($mailer, ['smtp', 'ses', 'postmark', 'resend'], true)) {
            return false;
        }

        $smtpIsConfigured = $mailer !== 'smtp'
            || (trim((string) config('mail.mailers.smtp.host')) !== ''
                && in_array(
                    filter_var(config('mail.mailers.smtp.port'), FILTER_VALIDATE_INT),
                    [25, 465, 587, 2525],
                    true,
                ));

        return $smtpIsConfigured
            && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false
            && $applicationUrlIsAddressable
            && is_string($signingKey) && trim($signingKey) !== ''
            && is_int($expirationMinutes);
    }

    private function registrationAllowed(): bool
    {
        return $this->policies->isEnabled('account_registration');
    }

    private function legacyLocalBypassEnabled(): bool
    {
        return config('app.env') === 'local'
            && config('oyafind.policies.local_registration_without_verification') === true;
    }
}
