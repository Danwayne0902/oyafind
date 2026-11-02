<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\VerificationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Search visibility regressions: structured data has to describe the real
 * listing (its country, its remote status) and internal links have to point
 * at pages that actually exist. These fixtures are synthetic and use the same
 * evidence rules as production.
 */
class SearchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    private Category $jobs;

    private Location $nigeria;

    private Location $lagos;

    private Location $remote;

    private User $staffReviewer;

    private User $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->now = CarbonImmutable::parse('2026-10-23 07:57:36', 'UTC');
        CarbonImmutable::setTestNow($this->now);

        $this->jobs = Category::query()->create([
            'name' => 'Jobs',
            'slug' => 'jobs',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->staffReviewer = User::query()->create(['name' => 'Synthetic reviewer']);
        $this->staffReviewer->forceFill(['role' => 'staff'])->save();

        $this->verifier = User::query()->create(['name' => 'Synthetic verifier']);
        $this->verifier->forceFill(['role' => 'staff'])->save();

        $this->nigeria = $this->location('Nigeria', 'nigeria', null, 'country');
        $this->lagos = $this->location('Lagos', 'lagos', $this->nigeria, 'state');
        $this->remote = $this->location('Remote / Online', 'remote', null, 'region');

        $this->enablePublicationPolicy();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_a_nigerian_job_uses_the_real_country_in_its_job_posting_schema(): void
    {
        config(['app.url' => 'https://oyafind.com.ng']);
        $listing = $this->eligibleListing('lagos-job', $this->lagos);

        $body = (string) $this->get('/opportunities/'.$listing->slug)->assertOk()->getContent();

        $schema = $this->schemaOfType($body, 'JobPosting');

        $this->assertNotNull($schema, 'The page should publish JobPosting structured data.');
        $this->assertSame('NG', $schema['jobLocation']['address']['addressCountry']);
        $this->assertSame('Lagos', $schema['jobLocation']['address']['addressLocality']);
        $this->assertArrayNotHasKey('jobLocationType', $schema);
        $this->assertSame('https://oyafind.com.ng/opportunities/lagos-job', $schema['url']);
        $this->assertFalse($schema['directApply']);
    }

    public function test_a_remote_job_is_marked_as_telecommute_instead_of_a_place(): void
    {
        $listing = $this->eligibleListing('remote-job', $this->remote);

        $schema = $this->schemaOfType(
            (string) $this->get('/opportunities/'.$listing->slug)->getContent(),
            'JobPosting',
        );

        $this->assertNotNull($schema);
        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertArrayNotHasKey('jobLocation', $schema);
    }

    public function test_pages_publish_one_publisher_and_link_listings_to_real_area_pages(): void
    {
        config(['app.url' => 'https://oyafind.com.ng']);
        $this->eligibleListing('area-link-job', $this->lagos);

        $body = (string) $this->get('/categories/jobs')->assertOk()->getContent();

        $schemas = $this->schemas($body);
        $types = array_map(static fn (array $schema): string => (string) ($schema['@type'] ?? ''), $schemas);

        $this->assertSame(1, count(array_keys($types, 'Organization', true)));
        $this->assertSame(1, count(array_keys($types, 'WebSite', true)));
        $this->assertStringContainsString('Jobs in Lagos', $body);
        $this->assertStringContainsString('/categories/jobs?location=lagos', $body);
    }

    public function test_the_feed_is_xml_and_only_contains_live_listings(): void
    {
        config(['app.url' => 'https://oyafind.com.ng']);

        $this->eligibleListing('feed-live-job', $this->lagos);
        $this->makeOpportunity('feed-unverified-job', $this->lagos);

        $response = $this->get('/feed.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<rss version="2.0"', false)
            ->assertSee('https://oyafind.com.ng/opportunities/feed-live-job', false);

        $this->assertStringNotContainsString('feed-unverified-job', (string) $response->getContent());
    }

    private function location(string $name, string $slug, ?Location $parent, string $type): Location
    {
        return Location::query()->create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parent?->id,
            'location_type' => $type,
            'is_active' => true,
            'reviewed_at' => $this->now,
            'reviewed_by' => $this->staffReviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);
    }

    private function makeOpportunity(string $slug, Location $location): Opportunity
    {
        return Opportunity::query()->create([
            'title' => 'Synthetic '.$slug,
            'slug' => $slug,
            'summary' => 'Synthetic public summary.',
            'description' => 'Synthetic public description used by a harmless test fixture.',
            'category_id' => $this->jobs->id,
            'location_id' => $location->id,
            'submitted_by' => $this->verifier->id,
            'organisation_name' => 'Synthetic organisation',
            'source_url' => 'https://example.test/source',
            'application_url' => 'https://example.test/apply',
            'application_method' => 'external',
            'application_instructions' => 'Synthetic instructions.',
            'opportunity_type' => 'Full-time',
            'work_mode' => 'onsite',
            'currency' => 'NGN',
            'status' => 'published',
            'published_at' => $this->now->subDays(2),
        ]);
    }

    private function eligibleListing(string $slug, Location $location): Opportunity
    {
        $opportunity = $this->makeOpportunity($slug, $location);

        DB::table('audit_logs')->insert([
            'actor_id' => $this->staffReviewer->id,
            'action' => 'opportunity.review.approved',
            'entity_type' => 'opportunity',
            'entity_id' => $opportunity->id,
            'metadata' => json_encode([
                'evidence_reference' => 'synthetic-review-reference',
                'rationale' => 'Synthetic staff reviewed this fixture.',
            ], JSON_THROW_ON_ERROR),
            'created_at' => $this->now->subDays(3)->format('Y-m-d H:i:s'),
        ]);

        VerificationRecord::query()->create([
            'opportunity_id' => $opportunity->id,
            'reviewer_id' => $this->verifier->id,
            'result' => 'verified',
            'check_type' => 'source_details',
            'evidence_url' => 'https://example.test/evidence',
            'evidence_rationale' => 'Synthetic source and details evidence.',
            'checked_at' => $this->now->subDays(3),
            'created_at' => $this->now->subDays(3),
        ]);

        return $opportunity;
    }

    private function enablePublicationPolicy(): void
    {
        config([
            'oyafind.discovery' => [
                'enabled' => true,
                'staff_review_action' => 'opportunity.review.approved',
                'staff_reviewer_ids' => [$this->staffReviewer->id],
                'staff_reviewer_roles' => ['staff'],
                'verification' => [
                    'reviewer_ids' => [$this->verifier->id],
                    'reviewer_roles' => ['staff'],
                    'allowed_check_types' => ['source_details'],
                    'required_check_types' => ['source_details'],
                    'require_distinct_reviewers' => true,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function schemaOfType(string $html, string $type): ?array
    {
        foreach ($this->schemas($html) as $schema) {
            if (($schema['@type'] ?? null) === $type) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function schemas(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches);

        $schemas = [];

        foreach ($matches[1] as $json) {
            $decoded = json_decode(html_entity_decode($json), true);

            if (is_array($decoded)) {
                $schemas[] = $decoded;
            }
        }

        return $schemas;
    }
}
