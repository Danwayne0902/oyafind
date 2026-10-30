<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Source;
use App\Models\SourceItem;
use App\Models\User;
use App\Models\VerificationRecord;
use App\Services\OpportunityPublicationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpportunityIngestionTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    private User $reviewer;

    private User $verifier;

    private Category $jobs;

    private Location $fct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC');
        CarbonImmutable::setTestNow($this->now);

        $this->jobs = Category::query()->create([
            'name' => 'Jobs',
            'slug' => 'jobs',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Category::query()->create([
            'name' => 'Tenders',
            'slug' => 'tenders',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Category::query()->create([
            'name' => 'Grants',
            'slug' => 'grants',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->reviewer = User::query()->create(['name' => 'Synthetic ingestion reviewer']);
        $this->reviewer->forceFill(['role' => 'admin', 'status' => 'active'])->save();

        $this->verifier = User::query()->create(['name' => 'Synthetic ingestion verifier']);
        $this->verifier->forceFill(['role' => 'staff', 'status' => 'active'])->save();

        $this->fct = Location::query()->create([
            'name' => 'Federal Capital Territory',
            'slug' => 'fct-abuja',
            'location_type' => 'state',
            'is_active' => true,
            'reviewed_at' => $this->now,
            'reviewed_by' => $this->reviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_rss_ingestion_stages_only_listing_like_items_and_scrubs_contacts(): void
    {
        $source = $this->makeSource();

        Http::fake([
            'jobs.example.test/feed' => Http::response($this->rssFeed(), 200, [
                'Content-Type' => 'application/rss+xml',
            ]),
        ]);

        $this->artisan('opportunities:ingest', ['--source' => $source->slug, '--limit' => 20])
            ->assertSuccessful();

        $pending = SourceItem::query()->where('status', SourceItem::STATUS_PENDING)->get();
        $this->assertCount(1, $pending);

        $item = $pending->first();
        $this->assertSame('Field Officer at Example Energy', $item->title);
        $this->assertSame('jobs', $item->category_slug);
        $this->assertSame($this->fct->id, $item->location_id);
        $this->assertSame('2026-11-06 23:59:59', $item->deadline_at->format('Y-m-d H:i:s'));
        $this->assertStringNotContainsString('hr@example.test', (string) $item->excerpt);
        $this->assertStringNotContainsString('0803 123 4567', (string) $item->excerpt);
        $this->assertStringNotContainsString('https://', (string) $item->excerpt);

        $this->assertDatabaseHas('source_items', [
            'title' => '10 Scholarships Currently Open',
            'status' => SourceItem::STATUS_FILTERED,
            'status_reason' => 'roundup',
        ]);
        $this->assertDatabaseHas('source_items', [
            'canonical_url' => 'https://unrelated.example/job',
            'status' => SourceItem::STATUS_FILTERED,
            'status_reason' => 'offsite_link',
        ]);
        $this->assertDatabaseHas('source_items', [
            'title' => 'A Sample Grant Proposal for a Community Fund',
            'status' => SourceItem::STATUS_FILTERED,
            'status_reason' => 'not_a_listing',
        ]);

        $this->assertSame(4, SourceItem::query()->count());
        $this->assertSame(5, $source->fresh()->last_item_count);
    }

    public function test_rss_ingestion_accepts_html_entities_and_raw_markup(): void
    {
        $source = $this->makeSource();

        $feed = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Messy but real</title>
    <item>
      <title>Graduate Trainee &mdash; Lagos</title>
      <link>https://jobs.example.test/graduate-trainee-lagos</link>
      <guid>messy-1</guid>
      <pubDate>Mon, 26 Oct 2026 08:00:00 +0000</pubDate>
      <description><p>A <strong>paid</strong> programme in Lagos &mdash; apply through the source.</p></description>
      <category>Graduate jobs</category>
    </item>
  </channel>
</rss>
XML;

        Http::fake([
            'jobs.example.test/feed' => Http::response($feed, 200, [
                'Content-Type' => 'application/rss+xml',
            ]),
        ]);

        $this->artisan('opportunities:ingest', ['--source' => $source->slug, '--limit' => 20])
            ->assertSuccessful();

        $this->assertDatabaseHas('source_items', [
            'canonical_url' => 'https://jobs.example.test/graduate-trainee-lagos',
            'status' => SourceItem::STATUS_PENDING,
            'title' => 'Graduate Trainee — Lagos',
        ]);
    }

    public function test_repeat_ingestion_deduplicates_by_canonical_url(): void
    {
        $source = $this->makeSource();

        Http::fake([
            'jobs.example.test/feed' => Http::response($this->rssFeed(), 200, [
                'Content-Type' => 'application/rss+xml',
            ]),
        ]);

        $this->artisan('opportunities:ingest', ['--source' => $source->slug])->assertSuccessful();
        $this->artisan('opportunities:ingest', ['--source' => $source->slug])->assertSuccessful();

        $this->assertSame(1, SourceItem::query()
            ->where('status', SourceItem::STATUS_PENDING)
            ->count());
    }

    public function test_world_bank_adapter_keeps_open_nigeria_notices_and_drops_contacts(): void
    {
        $source = Source::query()->create([
            'name' => 'Synthetic World Bank',
            'slug' => 'synthetic-world-bank',
            'homepage_url' => 'https://projects.worldbank.org',
            'feed_url' => 'https://search.worldbank.org/api/v2/procnotices?format=json&qterm=Nigeria',
            'adapter' => 'world_bank',
            'default_category_slug' => 'tenders',
            'timezone' => 'UTC',
            'attribution' => 'World Bank Group',
            'enabled' => true,
        ]);

        Http::fake([
            'search.worldbank.org/*' => Http::response([
                'procnotices' => [
                    [
                        'id' => 'OP-OPEN-NIGERIA',
                        'project_ctry_name' => 'Nigeria',
                        'notice_type' => 'Invitation for Bids',
                        'noticedate' => '23-Oct-2026',
                        'project_name' => 'Synthetic Nigeria Water Project',
                        'bid_description' => 'Construction of a solar-powered borehole',
                        'contact_organization' => 'Synthetic Ministry',
                        'contact_email' => 'procurement@example.gov.ng',
                        'contact_phone' => '+234 803 000 0000',
                        'submission_deadline_date' => '2030-11-20T00:00:00Z',
                        'submission_deadline_time' => '12:00',
                    ],
                    [
                        'id' => 'OP-AWARD-NIGERIA',
                        'project_ctry_name' => 'Nigeria',
                        'notice_type' => 'Contract Award',
                        'noticedate' => '23-Oct-2026',
                        'project_name' => 'Synthetic Award',
                    ],
                    [
                        'id' => 'OP-OPEN-KENYA',
                        'project_ctry_name' => 'Kenya',
                        'notice_type' => 'Invitation for Bids',
                        'noticedate' => '23-Oct-2026',
                        'project_name' => 'Synthetic Kenya Project',
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $this->artisan('opportunities:ingest', ['--source' => $source->slug])
            ->assertSuccessful();

        $items = SourceItem::query()->where('source_id', $source->id)->get();

        $this->assertCount(1, $items);
        $item = $items->first();
        $this->assertSame('tenders', $item->category_slug);
        $this->assertSame(
            'https://projects.worldbank.org/en/projects-operations/procurement-detail/OP-OPEN-NIGERIA',
            $item->canonical_url,
        );
        $this->assertSame('2030-11-20 12:00:00', $item->deadline_at->format('Y-m-d H:i:s'));
        $this->assertStringNotContainsString('procurement@example.gov.ng', (string) $item->excerpt);
        $this->assertStringNotContainsString('803 000 0000', (string) $item->excerpt);
    }

    public function test_publication_creates_evidence_and_is_visible_to_the_public_feed(): void
    {
        $this->enablePublicationPolicy();
        $item = $this->makePendingItem();

        $service = app(OpportunityPublicationService::class);
        $service->approve($item, $this->reviewer);

        $this->assertSame(SourceItem::STATUS_REVIEWED, $item->fresh()->status);
        $this->assertSame($this->reviewer->id, $item->fresh()->reviewed_by);
        $this->getJson('/api/v1/opportunities')->assertOk()->assertJsonCount(0, 'data');

        $opportunity = $service->publish($item->fresh(), $this->reviewer, $this->verifier);

        $this->assertSame('published', $opportunity->status);
        $this->assertSame($this->verifier->id, $opportunity->verified_by);
        $this->assertSame(SourceItem::STATUS_APPROVED, $item->fresh()->status);
        $this->assertSame($opportunity->id, $item->fresh()->opportunity_id);

        $this->assertSame(2, $opportunity->verificationRecords()->count());
        $this->assertSame(
            ['details_match', 'source_live'],
            $opportunity->verificationRecords()->orderBy('check_type')->pluck('check_type')->all(),
        );
        $this->assertTrue($opportunity->verificationRecords->every(
            fn (VerificationRecord $record): bool => $record->reviewer_id === $this->verifier->id
                && $record->evidence_url === $item->canonical_url,
        ));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->reviewer->id,
            'action' => 'staff_approved_ingested_listing',
            'entity_type' => 'opportunity',
            'entity_id' => $opportunity->id,
        ]);

        $this->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $opportunity->slug);
    }

    public function test_publication_rejects_reused_items_same_reviewer_and_missing_policy(): void
    {
        $service = app(OpportunityPublicationService::class);

        $this->expectException(RuntimeException::class);

        $this->enablePublicationPolicy();
        $item = $this->makePendingItem();

        $service->approve($item, $this->reviewer);
        $service->publish($item->fresh(), $this->reviewer, $this->verifier);
        $service->publish($item->fresh(), $this->reviewer, $this->verifier);
    }

    public function test_publication_requires_two_different_allowlisted_people(): void
    {
        $this->enablePublicationPolicy();
        config([
            'oyafind.discovery.verification.reviewer_ids' => [$this->reviewer->id, $this->verifier->id],
            'oyafind.discovery.verification.reviewer_roles' => ['admin', 'staff'],
        ]);

        $item = $this->makePendingItem();
        $service = app(OpportunityPublicationService::class);
        $service->approve($item, $this->reviewer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be different people');

        $service->publish($item->fresh(), $this->reviewer, $this->reviewer);
    }

    public function test_publication_fails_closed_without_an_approved_policy(): void
    {
        config(['oyafind.discovery.enabled' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Publication policy is not enabled');

        app(OpportunityPublicationService::class)
            ->publish($this->makePendingItem(), $this->reviewer, $this->verifier);
    }

    public function test_publishing_requires_a_staff_approval_first(): void
    {
        $this->enablePublicationPolicy();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only staff-approved candidates');

        app(OpportunityPublicationService::class)
            ->publish($this->makePendingItem(), $this->reviewer, $this->verifier);
    }

    public function test_expire_command_only_touches_pending_items_with_a_past_deadline(): void
    {
        $past = $this->makePendingItem([
            'title' => 'Past deadline listing',
            'canonical_url' => 'https://jobs.example.test/past-deadline',
            'url_hash' => hash('sha256', 'https://jobs.example.test/past-deadline'),
            'deadline_at' => $this->now->subDay(),
        ]);
        $future = $this->makePendingItem([
            'title' => 'Future deadline listing',
            'canonical_url' => 'https://jobs.example.test/future-deadline',
            'url_hash' => hash('sha256', 'https://jobs.example.test/future-deadline'),
            'deadline_at' => $this->now->addDay(),
        ]);
        $noDeadline = $this->makePendingItem([
            'title' => 'No deadline listing',
            'canonical_url' => 'https://jobs.example.test/no-deadline',
            'url_hash' => hash('sha256', 'https://jobs.example.test/no-deadline'),
            'deadline_at' => null,
        ]);

        $this->artisan('opportunities:expire')->assertSuccessful();

        $this->assertSame(SourceItem::STATUS_EXPIRED, $past->fresh()->status);
        $this->assertSame(SourceItem::STATUS_PENDING, $future->fresh()->status);
        $this->assertSame(SourceItem::STATUS_PENDING, $noDeadline->fresh()->status);
    }

    private function makeSource(): Source
    {
        return Source::query()->create([
            'name' => 'Synthetic jobs feed',
            'slug' => 'synthetic-jobs-feed',
            'homepage_url' => 'https://jobs.example.test',
            'feed_url' => 'https://jobs.example.test/feed',
            'adapter' => 'rss',
            'default_category_slug' => 'jobs',
            'timezone' => 'Africa/Lagos',
            'attribution' => 'Synthetic jobs feed',
            'enabled' => true,
        ]);
    }

    private function rssFeed(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Synthetic jobs</title>
    <item>
      <title>Field Officer at Example Energy</title>
      <link>https://jobs.example.test/field-officer-abuja</link>
      <guid>job-1</guid>
      <pubDate>Mon, 26 Oct 2026 06:00:00 +0000</pubDate>
      <description><![CDATA[<p>Example Energy is hiring a Field Officer in Abuja. Apply to hr@example.test or call 0803 123 4567. Application Deadline: 6 November 2026. Read more at https://jobs.example.test/apply.</p>]]></description>
    </item>
    <item>
      <title>10 Scholarships Currently Open</title>
      <link>https://jobs.example.test/scholarships-roundup</link>
      <guid>job-2</guid>
      <description><![CDATA[<p>A roundup of scholarships.</p>]]></description>
    </item>
    <item>
      <title>Remote Data Entry Job</title>
      <link>https://unrelated.example/job</link>
      <guid>job-3</guid>
      <description><![CDATA[<p>This item points off the source domain.</p>]]></description>
    </item>
    <item>
      <title>A Sample Grant Proposal for a Community Fund</title>
      <link>https://jobs.example.test/sample-grant-proposal</link>
      <guid>job-4</guid>
      <description><![CDATA[<p>A downloadable template, not an opportunity.</p>]]></description>
    </item>
    <item>
      <title>Field Officer at Example Energy</title>
      <link>https://jobs.example.test/field-officer-abuja?utm_source=rss</link>
      <guid>job-5</guid>
      <description><![CDATA[<p>The same job, syndicated twice.</p>]]></description>
    </item>
  </channel>
</rss>
XML;
    }

    private function enablePublicationPolicy(): void
    {
        config([
            'oyafind.discovery' => [
                'enabled' => true,
                'staff_review_action' => 'staff_approved_ingested_listing',
                'staff_reviewer_ids' => [$this->reviewer->id],
                'staff_reviewer_roles' => ['admin'],
                'verification' => [
                    'reviewer_ids' => [$this->verifier->id],
                    'reviewer_roles' => ['staff'],
                    'allowed_check_types' => ['source_live', 'details_match'],
                    'required_check_types' => ['source_live', 'details_match'],
                    'require_distinct_reviewers' => true,
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePendingItem(array $overrides = []): SourceItem
    {
        $source = Source::query()->firstOrCreate(
            ['slug' => 'synthetic-jobs-feed'],
            [
                'name' => 'Synthetic jobs feed',
                'homepage_url' => 'https://jobs.example.test',
                'feed_url' => 'https://jobs.example.test/feed',
                'adapter' => 'rss',
                'default_category_slug' => 'jobs',
                'timezone' => 'Africa/Lagos',
                'attribution' => 'Synthetic jobs feed',
                'enabled' => true,
            ],
        );

        return SourceItem::query()->create(array_merge([
            'source_id' => $source->id,
            'url_hash' => hash('sha256', 'https://jobs.example.test/field-officer-abuja'),
            'canonical_url' => 'https://jobs.example.test/field-officer-abuja',
            'title' => 'Field Officer at Example Energy',
            'organisation_name' => 'Example Energy',
            'summary' => 'Example Energy is hiring a Field Officer in Abuja.',
            'excerpt' => 'Example Energy is hiring a Field Officer in Abuja.',
            'published_at' => $this->now->subHours(2),
            'deadline_at' => $this->now->addWeek(),
            'location_text' => 'Abuja',
            'location_id' => $this->fct->id,
            'category_slug' => 'jobs',
            'status' => SourceItem::STATUS_PENDING,
            'dedupe_key' => hash('sha256', 'field officer at example energy|Example Energy'),
            'fetched_at' => $this->now,
        ], $overrides));
    }
}
