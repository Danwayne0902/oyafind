<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDiscoveryShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_deliver_the_vue_shell_with_server_rendered_metadata(): void
    {
        $this->withoutVite();

        foreach ([
            '/' => 'OyaFind',
            '/categories' => 'Browse opportunity categories',
            '/how-it-works' => 'How OyaFind checks every listing',
            '/search' => 'OyaFind',
        ] as $path => $expectedTitle) {
            $this->get($path)
                ->assertOk()
                ->assertSee('id="app"', false)
                ->assertSee('<title>'.$expectedTitle, false);
        }
    }

    public function test_sitemap_is_xml_with_absolute_canonical_urls_and_no_duplicates(): void
    {
        config(['app.url' => 'https://oyafind.com.ng']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee('https://oyafind.com.ng/', false)
            ->assertSee('https://oyafind.com.ng/categories', false);

        $body = $response->getContent();
        $locations = [];
        preg_match_all('#<loc>(.*?)</loc>#', $body, $locations);

        $this->assertNotEmpty($locations[1]);
        $this->assertSame($locations[1], array_values(array_unique($locations[1])));
    }

    public function test_robots_points_to_the_sitemap_and_keeps_private_areas_out(): void
    {
        $robots = (string) file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://oyafind.com.ng/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /api/', $robots);
    }

    public function test_unknown_category_and_listing_slugs_return_a_real_404(): void
    {
        $this->withoutVite();

        $this->get('/categories/not-a-real-category')
            ->assertNotFound()
            ->assertSee('Page not found');

        $this->get('/opportunities/not-a-real-listing')
            ->assertNotFound()
            ->assertSee('Page not found');
    }
}
