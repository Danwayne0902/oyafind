<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sign-in endpoint has two independent defences: a minute-long rate
 * limiter keyed by IP and by IP + identifier, and a durable per-account
 * lockout. These tests pin the lockout behaviour and the fact that both
 * layers are present.
 */
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failures_lock_the_account_even_after_the_rate_limit_window(): void
    {
        $user = $this->createUser('locked@example.test');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => $user->email,
                'password' => 'wrong password',
            ])->assertStatus(422);
        }

        // The fifth failure crosses the threshold and reports the lock.
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong password',
        ])->assertStatus(429);

        $this->assertDatabaseHas('login_attempts', [
            'identifier' => $user->email,
            'failed_attempts' => 5,
        ]);

        // Step past the per-minute limiter but stay inside the lockout window,
        // so a 429 here can only come from the account lockout itself.
        $this->travel(2)->minutes();

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'correct horse battery staple',
        ]);

        $response->assertStatus(429)->assertHeader('Retry-After');
        $this->assertStringContainsString('Too many failed sign-in attempts', $response->json('message'));
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_lockout_expires_and_a_correct_sign_in_clears_the_counter(): void
    {
        $user = $this->createUser('expires@example.test');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => $user->email,
                'password' => 'wrong password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong password',
        ])->assertStatus(429);

        $this->travel(16)->minutes();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'correct horse battery staple',
        ])->assertOk();

        $this->assertDatabaseCount('login_attempts', 0);
    }

    public function test_a_successful_sign_in_before_the_threshold_clears_earlier_failures(): void
    {
        $user = $this->createUser('clears@example.test');

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong password',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'correct horse battery staple',
        ])->assertOk();

        $this->assertDatabaseCount('login_attempts', 0);
    }

    public function test_one_locked_account_does_not_block_another_account(): void
    {
        $locked = $this->createUser('first@example.test');
        $healthy = $this->createUser('second@example.test');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => $locked->email,
                'password' => 'wrong password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $locked->email,
            'password' => 'wrong password',
        ])->assertStatus(429);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $healthy->email,
            'password' => 'correct horse battery staple',
        ])->assertOk();
    }

    public function test_the_ip_limiter_caps_attempts_even_when_every_identifier_is_new(): void
    {
        config(['oyafind.auth.ip_attempts_per_minute' => 10]);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => 'person'.$attempt.'@example.test',
                'password' => 'wrong password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'person11@example.test',
            'password' => 'wrong password',
        ])->assertStatus(429);
    }

    public function test_support_can_unlock_an_account_with_an_artisan_command(): void
    {
        $user = $this->createUser('support@example.test');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => $user->email,
                'password' => 'wrong password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong password',
        ])->assertStatus(429);

        $this->travel(2)->minutes();
        $this->artisan('auth:unlock', ['identifier' => 'SUPPORT@example.test'])->assertSuccessful();

        $this->travel(2)->minutes();
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'correct horse battery staple',
        ])->assertOk();
    }

    private function createUser(string $email): User
    {
        $user = User::query()->create([
            'name' => 'Synthetic account holder',
            'email' => $email,
            'password' => 'correct horse battery staple',
        ]);

        $user->forceFill([
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();

        return $user->fresh();
    }
}
