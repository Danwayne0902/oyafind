<?php

namespace Tests\Feature;

use App\Mail\VerifyAccountEmail;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AccountAuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_login_and_profile_screens_are_routed_to_the_vue_shell(): void
    {
        $this->withoutVite();

        foreach (['/register', '/login', '/profile', '/verify-email/1/'.str_repeat('a', 40)] as $path) {
            $this->get($path)->assertOk()->assertSee('OyaFind');
        }
    }

    public function test_registration_fails_closed_without_creating_accounts_or_enabling_delivery(): void
    {
        config([
            'app.env' => 'local',
            'oyafind.policies.account_registration' => false,
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'A controlled test user',
            'contact' => 'user@example.test',
            'password' => 'a sufficiently long test password',
            'password_confirmation' => 'a sufficiently long test password',
        ])
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Registration is currently closed. Please check back shortly.',
            );

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_email_verification_requires_a_working_mailer(): void
    {
        $this->getJson('/api/v1/auth/registration-status')
            ->assertOk()
            ->assertJsonPath('data.available', false);

        config([
            'oyafind.policies.account_registration' => true,
            'oyafind.registration.mode' => 'email_verification',
            'mail.default' => 'log',
            'mail.from.address' => 'hello@oyafind.com.ng',
            'app.url' => 'https://oyafind.com.ng',
            'app.env' => 'production',
        ]);

        $this->getJson('/api/v1/auth/registration-status')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath(
                'data.message',
                'Registration is temporarily unavailable because the verification email service is not configured. Please try again later.',
            );

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.eu.mailgun.org',
            'mail.mailers.smtp.port' => 587,
        ]);

        $this->getJson('/api/v1/auth/registration-status')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.mode', 'email_verification');

        config([
            'oyafind.registration.mode' => 'direct',
            'mail.default' => 'log',
        ]);

        $this->getJson('/api/v1/auth/registration-status')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.mode', 'direct');
    }

    public function test_email_verification_registration_is_unavailable_without_a_real_mailer(): void
    {
        config([
            'app.env' => 'local',
            'app.url' => 'http://oyafind.test',
            'oyafind.policies.account_registration' => true,
            'oyafind.registration.mode' => 'email_verification',
            'mail.default' => 'log',
            'mail.from.address' => 'hello@oyafind.com.ng',
        ]);
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->registrationPayload())
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Registration is temporarily unavailable because the verification email service is not configured. Please try again later.',
            );

        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_registration_creates_pending_account_and_sends_a_signed_verification_link(): void
    {
        $this->configureRegistration();
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->registrationPayload())
            ->assertAccepted()
            ->assertJsonPath(
                'message',
                'Check your inbox for a verification link, then sign in.',
            );

        $user = User::query()->where('email', 'user@example.test')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertSame('user', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame('correct horse battery staple', $user->password);
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'user@example.test',
            'password' => 'correct horse battery staple',
        ])->assertUnprocessable();

        Mail::assertSent(VerifyAccountEmail::class, function (VerifyAccountEmail $mail) use ($user): bool {
            $url = parse_url($mail->verificationUrl);
            parse_str($url['query'] ?? '', $query);

            $this->assertSame('user@example.test', $mail->user->email);
            $this->assertSame(60, $mail->expirationMinutes);
            $this->assertSame('/api/v1/auth/verify-email/'.$user->id.'/'.sha1($user->email), $url['path']);
            $this->assertArrayHasKey('signature', $query);
            $this->assertArrayHasKey('expires', $query);

            return true;
        });
    }

    public function test_signed_verification_requires_an_explicit_post_and_activates_only_the_pending_user(): void
    {
        $this->configureRegistration();
        Mail::fake();
        $this->postJson('/api/v1/auth/register', $this->registrationPayload())->assertAccepted();
        $user = User::query()->where('email', 'user@example.test')->firstOrFail();
        Mail::assertSent(VerifyAccountEmail::class);

        $verificationUrl = URL::temporarySignedRoute(
            'api.v1.auth.verify-email',
            now()->addMinutes(60),
            ['user' => $user->id, 'hash' => sha1($user->email)],
        );
        $relativeUrl = parse_url($verificationUrl, PHP_URL_PATH).'?'.parse_url($verificationUrl, PHP_URL_QUERY);

        $this->get($relativeUrl)
            ->assertRedirect()
            ->assertRedirectContains('/verify-email/'.$user->id.'/'.sha1($user->email));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('pending', $user->fresh()->status);

        $this->postJson($relativeUrl)
            ->assertOk()
            ->assertJsonPath('message', 'Your email is verified. You can now sign in to OyaFind.');

        $this->assertSame('active', $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'correct horse battery staple',
        ])->assertOk()->assertJsonPath('data.save_eligible', true);
    }

    public function test_expired_or_tampered_verification_links_are_rejected(): void
    {
        $user = $this->createUser([
            'email' => 'pending@example.test',
            'status' => 'pending',
        ]);
        $expiredUrl = URL::temporarySignedRoute(
            'api.v1.auth.verify-email',
            now()->subMinute(),
            ['user' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($expiredUrl)->assertForbidden();

        $validUrl = URL::temporarySignedRoute(
            'api.v1.auth.verify-email',
            now()->addMinutes(60),
            ['user' => $user->id, 'hash' => sha1($user->email)],
        );
        $tamperedUrl = str_replace(sha1($user->email), str_repeat('0', 40), $validUrl);

        $this->postJson($tamperedUrl)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('pending', $user->fresh()->status);
    }

    public function test_duplicate_email_and_phone_registration_returns_field_errors(): void
    {
        $pending = $this->createUser([
            'email' => 'USER@example.test',
            'status' => 'pending',
        ]);
        $phoneUser = $this->createUser([
            'phone' => '+2348012345678',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'contact' => 'USER@example.test',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact')
            ->assertJsonPath('errors.contact.0', 'An account with this email or phone number already exists. Sign in or use different details.');

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'contact' => '+234 801-234-5678',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact')
            ->assertJsonPath('errors.contact.0', 'An account with this email or phone number already exists. Sign in or use different details.');

        $this->assertDatabaseCount('users', 2);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('+2348012345678', $phoneUser->fresh()->phone);
    }

    public function test_registration_rejects_security_sensitive_fields(): void
    {
        $this->configureRegistration();

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now()->toISOString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'status', 'email_verified_at']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_validates_email_password_and_confirmation(): void
    {
        $this->configureRegistration();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'A controlled test user',
            'contact' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact', 'password']);

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'password_confirmation' => 'a different long password',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_registration_normalizes_contact_address(): void
    {
        $this->configureRegistration();
        Mail::fake();
        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'contact' => '  USER@EXAMPLE.TEST  ',
        ]))->assertAccepted();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'user@example.test']);
        Mail::assertSent(VerifyAccountEmail::class, 1);
    }

    public function test_direct_registration_creates_active_email_account_and_signs_user_in(): void
    {
        $this->configureLocalRegistrationBypass();
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->registrationPayload())
            ->assertCreated()
            ->assertJsonPath('registration_mode', 'direct')
            ->assertJsonPath('data.email', 'user@example.test')
            ->assertJsonPath('data.contact_verified', false)
            ->assertJsonPath('data.save_eligible', false);

        $user = User::query()->where('email', 'user@example.test')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame('correct horse battery staple', $user->password);
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
        Mail::assertNothingOutgoing();
    }

    public function test_direct_registration_creates_active_phone_account(): void
    {
        $this->configureLocalRegistrationBypass();
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'contact' => '+234 801-234-5678',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.phone', '+2348012345678')
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.contact_verified', false)
            ->assertJsonPath('data.save_eligible', false);

        $user = User::query()->where('phone', '+2348012345678')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->phone_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->postJson('/api/v1/auth/login', [
            'identifier' => '+234 801-234-5678',
            'password' => 'correct horse battery staple',
        ])->assertOk()->assertJsonPath('data.id', $user->id);
        Mail::assertNothingOutgoing();
    }

    public function test_direct_registration_is_available_in_production_and_creates_an_active_account(): void
    {
        config([
            'app.env' => 'production',
            'oyafind.policies.account_registration' => true,
            'oyafind.registration.mode' => 'direct',
        ]);
        Mail::fake();

        $this->getJson('/api/v1/auth/registration-status')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.mode', 'direct');

        $this->postJson('/api/v1/auth/register', $this->registrationPayload())
            ->assertCreated()
            ->assertJsonPath('registration_mode', 'direct')
            ->assertJsonPath('data.email', 'user@example.test');

        $this->assertDatabaseHas('users', [
            'email' => 'user@example.test',
            'status' => 'active',
            'email_verified_at' => null,
        ]);
        Mail::assertNothingOutgoing();
    }

    public function test_transport_failure_is_reported_and_removes_a_new_pending_account(): void
    {
        $this->configureRegistration();
        Log::spy();
        Mail::shouldReceive('to')
            ->once()
            ->with('user@example.test')
            ->andReturn(new class
            {
                public function send(VerifyAccountEmail $mail): void
                {
                    throw new TransportException('Synthetic SMTP failure.');
                }
            });

        $this->postJson('/api/v1/auth/register', $this->registrationPayload())
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'We could not send the verification message. Please try again in a moment.',
            );

        $this->assertDatabaseCount('users', 0);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('OyaFind verification email delivery failed.', [
                'user_id' => 1,
                'exception_type' => TransportException::class,
            ]);
    }

    public function test_email_login_starts_session_and_returns_only_safe_profile_fields(): void
    {
        $user = $this->createUser([
            'email' => 'person@example.test',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/auth/csrf-token')->assertOk()->assertJsonStructure(['csrf_token']);
        $sessionIdBeforeLogin = $this->app['session.store']->getId();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'PERSON@example.test',
            'password' => 'correct horse battery staple',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.contact_verified', true)
            ->assertJsonPath('data.save_eligible', true)
            ->assertJsonMissingPath('data.role')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.email_verified_at');

        $this->assertNotSame($sessionIdBeforeLogin, $this->app['session.store']->getId());
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'person@example.test');
    }

    public function test_phone_login_and_unverified_contact_are_supported_without_verification_delivery(): void
    {
        $user = $this->createUser([
            'phone' => '+2348012345678',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => '+2348012345678',
            'password' => 'correct horse battery staple',
        ])
            ->assertOk()
            ->assertJsonPath('data.contact_verified', false)
            ->assertJsonPath('data.save_eligible', false);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '+2348012345678']);
    }

    public function test_login_validates_email_or_phone_identifier_shape(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'not an email or phone',
            'password' => 'correct horse battery staple',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identifier');
    }

    public function test_verifying_an_ambiguous_secondary_contact_does_not_unlock_saves(): void
    {
        $user = $this->createUser([
            'email' => 'both@example.test',
            'phone' => '+2348012345678',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->signIn($user);
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.contact_verified', false)
            ->assertJsonPath('data.save_eligible', false);
    }

    public function test_login_failures_do_not_disclose_account_existence_or_suspension(): void
    {
        $suspended = $this->createUser([
            'email' => 'suspended@example.test',
            'status' => 'suspended',
        ]);

        $unknown = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'missing@example.test',
            'password' => 'wrong password',
        ]);
        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'suspended@example.test',
            'password' => 'wrong password',
        ]);
        $suspendedLogin = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'suspended@example.test',
            'password' => 'correct horse battery staple',
        ]);

        $unknown->assertStatus(422);
        $wrongPassword->assertStatus(422);
        $suspendedLogin->assertStatus(422);
        $this->assertSame($unknown->getContent(), $wrongPassword->getContent());
        $this->assertSame($unknown->getContent(), $suspendedLogin->getContent());
        $this->assertSame('suspended', $suspended->fresh()->status);
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_repeated_failed_logins_for_one_identifier_are_throttled(): void
    {
        $this->createUser([
            'email' => 'throttle@example.test',
            'status' => 'active',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => 'throttle@example.test',
                'password' => 'wrong password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'throttle@example.test',
            'password' => 'correct horse battery staple',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_sign_out_invalidates_the_session_and_protected_profile_access(): void
    {
        $this->signIn($this->createUser([
            'email' => 'logout@example.test',
            'status' => 'active',
        ]));
        $sessionIdBeforeLogout = $this->app['session.store']->getId();

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'You have been signed out.');

        $this->assertNotSame($sessionIdBeforeLogout, $this->app['session.store']->getId());
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_profile_updates_only_allowlisted_fields_and_optional_reviewed_preferences(): void
    {
        $user = $this->createUser([
            'email' => 'profile@example.test',
            'status' => 'active',
        ]);
        $category = Category::query()->create([
            'name' => 'Synthetic interest',
            'slug' => 'synthetic-interest',
            'is_active' => true,
        ]);
        $location = $this->createReviewedLocation();

        $this->signIn($user);
        $this->patchJson('/api/v1/me', [
            'name' => 'Updated profile name',
            'interests' => [$category->id],
            'home_location_id' => $location->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated profile name')
            ->assertJsonPath('data.interests.0', $category->id)
            ->assertJsonPath('data.home_location_id', $location->id);

        $this->patchJson('/api/v1/me', [
            'name' => 'Attempted privilege change',
            'role' => 'admin',
            'status' => 'suspended',
            'email_verified_at' => now()->toISOString(),
            'phone_verified_at' => now()->toISOString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
                'status',
                'email_verified_at',
                'phone_verified_at',
            ]);

        $this->assertSame('Updated profile name', $user->fresh()->name);
        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame('active', $user->fresh()->status);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_unreviewed_home_area_and_inactive_interests_are_rejected(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);

        $user = $this->createUser([
            'email' => 'preferences@example.test',
            'status' => 'active',
        ]);
        $inactiveCategory = Category::query()->create([
            'name' => 'Inactive interest',
            'slug' => 'inactive-interest',
            'is_active' => false,
        ]);
        $unreviewedLocation = Location::query()->create([
            'name' => 'Synthetic unreviewed area',
            'slug' => 'synthetic-unreviewed-area',
            'is_active' => false,
        ]);

        $this->signIn($user);
        $this->patchJson('/api/v1/me', [
            'interests' => [$inactiveCategory->id],
            'home_location_id' => $unreviewedLocation->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['interests.0', 'home_location_id']);
    }

    public function test_user_model_does_not_mass_assign_security_sensitive_attributes(): void
    {
        $user = User::query()->create([
            'name' => 'Mass assignment test',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        $this->assertSame('user', $user->role);
        $this->assertNull($user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes = []): User
    {
        $user = User::query()->create([
            'name' => 'Synthetic account holder',
            'password' => 'correct horse battery staple',
        ]);

        if ($attributes !== []) {
            $user->forceFill($attributes)->save();
        }

        return $user->fresh();
    }

    private function createReviewedLocation(): Location
    {
        config(['oyafind.policies.location_catalogue' => true]);
        $reviewer = $this->createUser(['name' => 'Synthetic area reviewer']);

        return Location::query()->create([
            'name' => 'Synthetic reviewed area',
            'slug' => 'synthetic-reviewed-area',
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);
    }

    private function signIn(User $user): void
    {
        $identifier = $user->email ?? $user->phone;

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $identifier,
            'password' => 'correct horse battery staple',
        ])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'A controlled test user',
            'contact' => 'user@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ], $overrides);
    }

    private function configureRegistration(): void
    {
        config([
            'oyafind.policies.account_registration' => true,
            'oyafind.policies.local_registration_without_verification' => false,
            'oyafind.email_verification.link_expiration_minutes' => 60,
            'app.env' => 'local',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.username' => 'sandbox-user',
            'mail.mailers.smtp.password' => 'synthetic-secret',
            'mail.from.address' => 'hello@oyafind.test',
            'app.url' => 'http://oyafind.test',
        ]);
    }

    private function configureLocalRegistrationBypass(): void
    {
        config([
            'app.env' => 'local',
            'app.url' => 'http://oyafind.test',
            'oyafind.policies.account_registration' => true,
            'oyafind.registration.mode' => 'direct',
        ]);
    }
}
