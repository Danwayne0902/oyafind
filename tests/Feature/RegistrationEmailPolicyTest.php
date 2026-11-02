<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Disposable inboxes are rejected at the only place they can enter the
 * system: the contact field during registration. Login stays untouched so an
 * account that already exists is never locked out by a new rule.
 */
class RegistrationEmailPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'local',
            'app.url' => 'http://oyafind.test',
            'oyafind.policies.account_registration' => true,
            'oyafind.registration.mode' => 'direct',
        ]);
    }

    public function test_a_disposable_email_domain_is_rejected_without_creating_an_account(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload('throwaway@mailinator.com'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('contact');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_subdomain_of_a_disposable_domain_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload('throwaway@inbox.mailinator.com'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('contact');
    }

    public function test_a_conventional_email_domain_is_accepted(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload('person@gmail.com'))
            ->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'person@gmail.com']);
    }

    public function test_a_phone_number_is_not_treated_as_an_email_domain(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload('+2348031234567'))
            ->assertStatus(201);

        $this->assertDatabaseHas('users', ['phone' => '+2348031234567']);
    }

    public function test_an_owner_configured_domain_is_rejected_as_well(): void
    {
        config(['oyafind.registration_blocked_email_domains' => ['not-allowed.example']]);

        $this->postJson('/api/v1/auth/register', $this->payload('person@not-allowed.example'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('contact');
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $contact): array
    {
        return [
            'name' => 'A controlled test user',
            'contact' => $contact,
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ];
    }
}
