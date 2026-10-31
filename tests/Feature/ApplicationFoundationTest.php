<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_application_shell_is_available_without_geography_policy(): void
    {
        config(['oyafind.policies.location_catalogue' => null]);

        $this->withoutMiddleware();
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('OyaFind');
    }

    public function test_versioned_api_health_route_is_available_without_geography_policy(): void
    {
        config(['oyafind.policies.location_catalogue' => null]);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_mysql_connection_uses_the_configured_oyafind_database_name(): void
    {
        $this->assertSame('oyafind', config('database.connections.mysql.database'));
    }

    public function test_tests_use_disposable_in_memory_sqlite_by_default(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
