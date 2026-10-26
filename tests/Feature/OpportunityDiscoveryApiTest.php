<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\OpportunityReport;
use App\Models\User;
use App\Models\VerificationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpportunityDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    private Category $category;

    private User $staffReviewer;

    private User $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-10-23 07:57:36', 'UTC');
        CarbonImmutable::setTestNow($this->now);

        $this->category = Category::query()->create([
            'name' => 'Synthetic jobs',
            'slug' => 'synthetic-jobs',
            'description' => 'Synthetic public taxonomy.',
            'icon_key' => 'briefcase',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->staffReviewer = User::query()->create(['name' => 'Synthetic staff reviewer']);
        $this->staffReviewer->forceFill(['role' => 'staff'])->save();
        $this->verifier = User::query()->create(['name' => 'Synthetic source verifier']);
        $this->verifier->forceFill(['role' => 'staff'])->save();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_missing_publication_policy_fails_closed_even_for_forged_listing_fields(): void
    {
        $this->makeOpportunity([
            'verification_status' => 'verified',
            'verified_at' => $this->now,
            'verified_by' => $this->verifier->id,
        ]);

        config(['oyafind.discovery.enabled' => null]);

        $this->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);

        config(['oyafind.discovery.enabled' => true]);
        $this->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/opportunities/synthetic-opportunity')
            ->assertNotFound()
            ->assertJsonPath('message', 'Opportunity not found.');

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'synthetic-jobs');
    }

    public function test_publication_requires_attributable_staff_review_and_latest_distinct_evidence(): void
    {
        $this->enableSyntheticPublicationPolicy();

        $reviewOnly = $this->makeOpportunity(['slug' => 'review-only']);
        $this->addStaffReview($reviewOnly);

        $noAudit = $this->makeOpportunity(['slug' => 'no-audit']);
        $this->addVerification($noAudit);

        $sameReviewer = $this->makeOpportunity(['slug' => 'same-reviewer']);
        $this->addStaffReview($sameReviewer);
        $this->addVerification($sameReviewer, ['reviewer_id' => $this->staffReviewer->id]);

        $missingEvidence = $this->makeOpportunity(['slug' => 'missing-evidence']);
        $this->addStaffReview($missingEvidence, ['metadata' => ['note' => 'not an evidence reference']]);
        $this->addVerification($missingEvidence);

        $malformedReviewEvidence = $this->makeOpportunity(['slug' => 'malformed-review-evidence']);
        $this->addStaffReview($malformedReviewEvidence, [
            'metadata' => [
                'evidence_reference' => ['untrusted' => 'object'],
                'rationale' => true,
            ],
        ]);
        $this->addVerification($malformedReviewEvidence);

        $invalidVerificationEvidence = $this->makeOpportunity(['slug' => 'invalid-verification-evidence']);
        $this->addStaffReview($invalidVerificationEvidence);
        DB::table('opportunity_verifications')->insert([
            'opportunity_id' => $invalidVerificationEvidence->id,
            'reviewer_id' => $this->verifier->id,
            'result' => 'verified',
            'check_type' => 'source_details',
            'evidence_url' => null,
            'evidence_summary' => null,
            'evidence_rationale' => null,
            'restricted_notes' => null,
            'checked_at' => $invalidVerificationEvidence->published_at->format('Y-m-d H:i:s'),
            'created_at' => $invalidVerificationEvidence->published_at->format('Y-m-d H:i:s'),
        ]);

        $negativeLatestCheck = $this->makeOpportunity(['slug' => 'negative-latest-check']);
        $this->addCompleteEvidence($negativeLatestCheck);
        $this->addVerification($negativeLatestCheck, [
            'result' => 'unverified',
            'checked_at' => $this->now->subHours(1),
        ]);

        $verifiedAfterPublication = $this->makeOpportunity([
            'slug' => 'verified-after-publication',
            'published_at' => $this->now->subDay(),
        ]);
        $this->addCompleteEvidence($verifiedAfterPublication, [
            'checked_at' => $this->now,
        ]);

        $eligible = $this->makeOpportunity([
            'slug' => 'eligible-synthetic-opportunity',
            'verification_status' => 'unverified',
            'verified_at' => null,
            'verified_by' => null,
        ]);
        $this->addCompleteEvidence($eligible);

        $response = $this->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'eligible-synthetic-opportunity')
            ->assertJsonPath('data.0.verification_status', 'verified')
            ->assertJsonPath('data.0.verified_at', $this->now->subDays(2)->toIso8601String());
        $response->assertJsonMissing(['slug' => 'verified-after-publication']);

        $response->assertJsonMissingPath('data.0.verified_by');
        $response->assertJsonMissingPath('data.0.submitted_by');
        $response->assertJsonMissingPath('data.0.source_verification');
        $response->assertJsonMissingPath('data.0.application_instructions_private');
    }

    public function test_lifecycle_flags_deadlines_and_no_deadline_recheck_window_are_enforced_in_utc(): void
    {
        $this->enableSyntheticPublicationPolicy();

        $this->makeEligible('overdue', [
            'deadline_at' => '2026-10-23 07:57:35',
        ]);
        $this->makeEligible('deadline-still-open', [
            'deadline_at' => '2026-10-23 08:00:00',
        ]);
        $this->makeEligible('draft', ['status' => 'draft']);
        $this->makeEligible('pending-review', ['status' => 'pending_review']);
        $this->makeEligible('rejected', ['status' => 'rejected']);
        $this->makeEligible('expired', ['status' => 'expired']);
        $this->makeEligible('archived', ['status' => 'archived']);
        $this->makeEligible('deleted', [], true);
        $this->makeOpportunity([
            'slug' => 'published-without-publication-time',
            'published_at' => null,
        ]);
        $futurePublished = $this->makeOpportunity([
            'slug' => 'future-published',
            'published_at' => $this->now->addDay(),
        ]);
        $this->addStaffReview($futurePublished, ['created_at' => $this->now]);
        $this->addVerification($futurePublished, [
            'checked_at' => $this->now,
            'created_at' => $this->now,
        ]);
        $this->makeEligible('verification-flagged', [
            'verification_status' => 'flagged',
        ]);

        $flagged = $this->makeEligible('flagged');
        OpportunityReport::query()->create([
            'opportunity_id' => $flagged->id,
            'reason' => 'synthetic flag',
            'status' => 'flagged',
        ]);

        $stale = $this->makeOpportunity([
            'slug' => 'stale-no-deadline',
            'published_at' => $this->now->subDays(31),
        ]);
        $this->addCompleteEvidence($stale, ['checked_at' => $this->now->subDays(31)]);

        $rechecked = $this->makeOpportunity([
            'slug' => 'recently-rechecked',
            'published_at' => $this->now->subDays(45),
        ]);
        $this->addCompleteEvidence($rechecked);
        $this->addVerification($rechecked, ['checked_at' => $this->now->subDays(10)]);

        $recentPublication = $this->makeOpportunity([
            'slug' => 'recent-publication',
            'published_at' => $this->now->subDays(20),
        ]);
        $this->addCompleteEvidence($recentPublication, ['checked_at' => $this->now->subDays(21)]);

        $this->getJson('/api/v1/opportunities?sort=title')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'deadline-still-open')
            ->assertJsonFragment(['slug' => 'recent-publication'])
            ->assertJsonFragment(['slug' => 'recently-rechecked'])
            ->assertJsonMissing(['slug' => 'overdue'])
            ->assertJsonMissing(['slug' => 'stale-no-deadline'])
            ->assertJsonMissing(['slug' => 'flagged'])
            ->assertJsonMissing(['slug' => 'draft'])
            ->assertJsonMissing(['slug' => 'published-without-publication-time'])
            ->assertJsonMissing(['slug' => 'future-published'])
            ->assertJsonMissing(['slug' => 'verification-flagged'])
            ->assertJsonMissing(['slug' => 'deleted']);
    }

    public function test_filters_sort_bounded_pagination_and_validation_errors(): void
    {
        $this->enableSyntheticPublicationPolicy();
        $this->makeEligible('zulu-role', [
            'title' => 'Zulu role',
            'summary' => 'Synthetic northern search phrase',
            'work_mode' => 'remote',
        ]);
        $this->makeEligible('alpha-role', [
            'title' => 'Alpha role',
            'summary' => 'Synthetic local listing',
            'work_mode' => 'on-site',
        ]);
        $this->makeEligible('beta-role', [
            'title' => 'Beta role',
            'summary' => 'Synthetic northern search phrase',
            'work_mode' => 'remote',
        ]);

        $this->getJson('/api/v1/opportunities?q=northern&category=synthetic-jobs&work_mode=remote&sort=title&per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'beta-role')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/v1/opportunities?sort=title&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'beta-role');

        $this->getJson('/api/v1/opportunities?sort=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
        $this->getJson('/api/v1/opportunities?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/opportunities?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
        $this->getJson('/api/v1/opportunities?unknown_filter=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unknown_filter');
        $this->getJson('/api/v1/opportunities?category=not-approved')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_unfiltered_browsing_needs_no_location_policy_but_location_filter_requires_reviewed_fixture(): void
    {
        $this->enableSyntheticPublicationPolicy();
        $opportunity = $this->makeEligible('location-bound-role');

        config(['oyafind.policies.location_catalogue' => null]);
        $this->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.location', null);
        $this->getJson('/api/v1/opportunities?location=synthetic-area')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('location');

        $reviewer = User::query()->create(['name' => 'Synthetic area reviewer']);
        $reviewer->forceFill(['role' => 'staff'])->save();
        $location = Location::query()->create([
            'name' => 'Synthetic area',
            'slug' => 'synthetic-area',
            'location_type' => 'test-area',
            'is_active' => true,
            'reviewed_at' => $this->now,
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);
        $opportunity->forceFill(['location_id' => $location->id])->save();

        config(['oyafind.policies.location_catalogue' => true]);
        $this->getJson('/api/v1/opportunities?location=synthetic-area')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.location.slug', 'synthetic-area')
            ->assertJsonMissingPath('data.0.location.reviewed_by');
    }

    public function test_state_filter_includes_opportunities_recorded_in_its_lgas(): void
    {
        $this->enableSyntheticPublicationPolicy();
        $reviewer = User::query()->create(['name' => 'Synthetic geography reviewer']);
        $reviewer->forceFill(['role' => 'staff'])->save();

        $state = Location::query()->create([
            'name' => 'Synthetic State',
            'slug' => 'synthetic-state',
            'location_type' => 'state',
            'is_active' => true,
            'reviewed_at' => $this->now,
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);
        $lga = Location::query()->create([
            'name' => 'Synthetic LGA',
            'slug' => 'synthetic-lga',
            'parent_id' => $state->id,
            'location_type' => 'lga',
            'is_active' => true,
            'reviewed_at' => $this->now,
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);
        Location::query()->create([
            'name' => 'Other State',
            'slug' => 'other-state',
            'location_type' => 'state',
            'is_active' => true,
            'reviewed_at' => $this->now,
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);

        $opportunity = $this->makeEligible('lga-bound-role');
        $opportunity->forceFill(['location_id' => $lga->id])->save();

        config(['oyafind.policies.location_catalogue' => true]);

        $this->getJson('/api/v1/opportunities?location=synthetic-state')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.location.slug', 'synthetic-lga');

        $this->getJson('/api/v1/opportunities?location=other-state')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_category_and_opportunity_resources_are_allowlisted_and_detail_uses_stable_slug(): void
    {
        $this->enableSyntheticPublicationPolicy();
        $this->makeEligible('shareable-synthetic-slug', [
            'source_url' => 'https://example.test/source',
            'application_url' => 'https://example.test/apply',
            'eligibility' => 'Synthetic eligibility details',
        ]);
        Category::query()->create([
            'name' => 'Inactive internal category',
            'slug' => 'inactive-category',
            'description' => 'Must remain hidden.',
            'icon_key' => 'internal-only',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $detail = $this->getJson('/api/v1/opportunities/shareable-synthetic-slug')
            ->assertOk()
            ->assertJsonPath('data.slug', 'shareable-synthetic-slug')
            ->assertJsonPath('data.source_url', 'https://example.test/source')
            ->assertJsonPath('data.eligibility', 'Synthetic eligibility details');

        $this->assertSame([
            'id', 'title', 'slug', 'summary', 'description', 'category', 'location',
            'organisation_name', 'source_url', 'application_url', 'application_method',
            'application_instructions', 'opportunity_type', 'work_mode', 'eligibility',
            'requirements', 'amount_min', 'amount_max', 'currency', 'deadline_at',
            'publication_status', 'verification_status', 'verified_at', 'published_at',
        ], array_keys($detail->json('data')));
        $detail->assertJsonMissingPath('data.submitted_by');
        $detail->assertJsonMissingPath('data.verified_by');
        $detail->assertJsonMissingPath('data.application_trackers');
        $detail->assertJsonMissingPath('data.verification_records');

        $categories = $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'synthetic-jobs');
        $this->assertSame(['id', 'name', 'slug', 'description', 'icon_key'], array_keys($categories->json('data.0')));

        $this->getJson('/api/v1/opportunities/unknown-stable-slug')->assertNotFound();
        $this->getJson('/api/v1/opportunities?work_mode=remote')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    private function enableSyntheticPublicationPolicy(): void
    {
        // These identities and check names are synthetic test-only allowlists.
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
     * @param  array<string, mixed>  $overrides
     */
    private function makeOpportunity(array $overrides = [], bool $softDelete = false): Opportunity
    {
        $publishedAt = $overrides['published_at'] ?? $this->now->subDays(2);
        $opportunity = Opportunity::query()->create(array_merge([
            'title' => 'Synthetic opportunity',
            'slug' => 'synthetic-opportunity',
            'summary' => 'Synthetic public summary.',
            'description' => 'Synthetic public description containing a harmless test fixture.',
            'category_id' => $this->category->id,
            'submitted_by' => $this->verifier->id,
            'organisation_name' => 'Synthetic organisation',
            'source_url' => 'https://example.test/source',
            'application_url' => 'https://example.test/apply',
            'application_method' => 'external',
            'application_instructions' => 'Synthetic public instructions.',
            'opportunity_type' => 'job',
            'work_mode' => 'remote',
            'eligibility' => 'Synthetic eligibility',
            'requirements' => ['Synthetic requirement'],
            'currency' => 'NGN',
            'status' => 'published',
            'verification_status' => null,
            'published_at' => $publishedAt,
            'verified_at' => null,
            'verified_by' => null,
        ], $overrides));

        if ($softDelete) {
            $opportunity->delete();
        }

        return $opportunity;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEligible(string $slug, array $overrides = [], bool $softDelete = false): Opportunity
    {
        $opportunity = $this->makeOpportunity(array_merge(['slug' => $slug], $overrides), $softDelete);
        $this->addCompleteEvidence($opportunity);

        return $opportunity;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addCompleteEvidence(Opportunity $opportunity, array $overrides = []): void
    {
        $this->addStaffReview($opportunity);
        $this->addVerification($opportunity, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addStaffReview(Opportunity $opportunity, array $overrides = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $overrides['actor_id'] ?? $this->staffReviewer->id,
            'action' => 'opportunity.review.approved',
            'entity_type' => 'opportunity',
            'entity_id' => $opportunity->id,
            'metadata' => json_encode($overrides['metadata'] ?? [
                'evidence_reference' => 'synthetic-review-reference',
                'rationale' => 'Synthetic staff reviewed this fixture.',
            ], JSON_THROW_ON_ERROR),
            'created_at' => ($overrides['created_at'] ?? $opportunity->published_at->subHour())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addVerification(Opportunity $opportunity, array $overrides = []): void
    {
        VerificationRecord::query()->create([
            'opportunity_id' => $opportunity->id,
            'reviewer_id' => $overrides['reviewer_id'] ?? $this->verifier->id,
            'result' => $overrides['result'] ?? 'verified',
            'check_type' => 'source_details',
            'evidence_url' => 'https://example.test/evidence',
            'evidence_rationale' => 'Synthetic source and details evidence.',
            'checked_at' => $overrides['checked_at'] ?? $opportunity->published_at,
            'created_at' => $overrides['created_at'] ?? $overrides['checked_at'] ?? $opportunity->published_at,
        ]);
    }
}
