<?php

namespace Tests\Unit;

use App\Support\PolicyConfiguration;
use InvalidArgumentException;
use Tests\TestCase;

class PolicyConfigurationTest extends TestCase
{
    public function test_missing_feature_policy_is_disabled(): void
    {
        config(['oyafind.policies.location_catalogue' => null]);

        $this->assertFalse(app(PolicyConfiguration::class)->isEnabled('location_catalogue'));
    }

    public function test_feature_switch_requires_a_boolean_value(): void
    {
        config(['oyafind.policies.account_registration' => 'yes']);

        $this->expectException(InvalidArgumentException::class);

        app(PolicyConfiguration::class)->isEnabled('account_registration');
    }

    public function test_feature_switch_accepts_an_explicit_boolean(): void
    {
        config(['oyafind.policies.account_registration' => true]);

        $this->assertTrue(app(PolicyConfiguration::class)->isEnabled('account_registration'));
    }

    public function test_policy_value_must_match_the_approved_allowlist(): void
    {
        config(['oyafind.policies.account_identifier' => 'email']);
        $policy = app(PolicyConfiguration::class);

        $this->assertSame('email', $policy->approvedValue('account_identifier', ['email']));

        config(['oyafind.policies.account_identifier' => 'phone']);

        $this->expectException(InvalidArgumentException::class);

        $policy->approvedValue('account_identifier', ['email']);
    }

    public function test_missing_approved_value_remains_unset(): void
    {
        config(['oyafind.policies.account_identifier' => null]);

        $this->assertNull(
            app(PolicyConfiguration::class)->approvedValue('account_identifier', ['email'])
        );
    }
}
