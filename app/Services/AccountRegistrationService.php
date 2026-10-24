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
    public function __construct(
        private readonly PolicyConfiguration $policies,
    ) {}

    public function isAvailable(): bool
    {
        if (! $this->localRegistrationAllowed()) {
            return false;
        }

        if ($this->localVerificationBypassEnabled()) {
            return true;
        }

        $fromAddress = (string) config('mail.from.address');
        $smtpHost = trim((string) config('mail.mailers.smtp.host'));
        $smtpUsername = trim((string) config('mail.mailers.smtp.username'));
        $smtpPassword = (string) config('mail.mailers.smtp.password');
        $smtpPort = filter_var(config('mail.mailers.smtp.port'), FILTER_VALIDATE_INT);
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
            && ! in_array(strtolower($applicationHost), ['localhost', '127.0.0.1', '::1'], true)
            && str_ends_with(strtolower($applicationHost), '.test');
        $signingKeyIsConfigured = is_string($signingKey) && trim($signingKey) !== '';

        return config('mail.default') === 'smtp'
            && $smtpHost === 'sandbox.smtp.mailtrap.io'
            && in_array($smtpPort, [25, 465, 587, 2525], true)
            && $smtpUsername !== ''
            && $smtpPassword !== ''
            && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false
            && $applicationUrlIsAddressable
            && $signingKeyIsConfigured
            && is_int($expirationMinutes);
    }

    public function registrationMode(): string
    {
        return $this->localVerificationBypassEnabled()
            ? 'local_unverified'
            : 'email_verification';
    }

    public function unavailableMessage(): string
    {
        if (! $this->localRegistrationAllowed()) {
            return 'Registration is disabled. It can only be enabled in local development with the account registration feature flag.';
        }

        if (! $this->localVerificationBypassEnabled()) {
            return 'Mailtrap Email Sandbox is not fully configured. Set MAIL_MAILER=smtp, MAIL_HOST=sandbox.smtp.mailtrap.io, MAIL_PORT, MAIL_USERNAME and MAIL_PASSWORD in your local .env, and ensure APP_KEY and APP_URL=http://oyafind.test are set. No email has been sent.';
        }

        return 'Local registration without verification is enabled.';
    }

    public function registerLocally(string $name, string $contact, string $password): User
    {
        if (! $this->localVerificationBypassEnabled()) {
            throw new \LogicException('Local registration without verification is not enabled.');
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

    private function localRegistrationAllowed(): bool
    {
        return config('app.env') === 'local'
            && $this->policies->isEnabled('account_registration');
    }

    private function localVerificationBypassEnabled(): bool
    {
        return $this->localRegistrationAllowed()
            && config('oyafind.policies.local_registration_without_verification') === true;
    }
}
