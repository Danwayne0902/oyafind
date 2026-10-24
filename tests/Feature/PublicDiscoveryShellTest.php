<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicDiscoveryShellTest extends TestCase
{
    public function test_public_discovery_routes_deliver_the_vue_application_shell(): void
    {
        $this->withoutMiddleware();
        $this->withoutVite();

        foreach ([
            '/',
            '/categories',
            '/how-it-works',
            '/search?q=training',
            '/categories/training',
            '/opportunities/public-role',
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('id="app"', false)
                ->assertSee('OyaFind — Real opportunities. Closer to you.');
        }
    }
}
