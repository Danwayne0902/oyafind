<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\VerificationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SavedAndTrackerApiTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    private Category $category;

    private User $staffReviewer;

    private User $verifier;

    private User $verifiedUser;

    private User $unverifiedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC');
        CarbonImmutable::setTestNow($this->now);

        $this->category = Category::query()->create([
            'name' => 'Synthetic Category',
            'slug' => 'synthetic-category',
            'description' => 'Test Category',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->staffReviewer = User::query()->create([
            'name' => 'Reviewer User',
            'email' => 'reviewer@example.test',
        ]);
        $this->staffReviewer->forceFill(['role' => 'staff'])->save();

        $this->verifier = User::query()->create([
            'name' => 'Verifier User',
            'email' => 'verifier@example.test',
        ]);
        $this->verifier->forceFill(['role' => 'staff'])->save();

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

        $this->verifiedUser = User::query()->create([
            'name' => 'Verified Seeker',
            'email' => 'verified@example.test',
        ]);
        $this->verifiedUser->forceFill([
            'status' => 'active',
            'email_verified_at' => $this->now,
        ])->save();

        $this->unverifiedUser = User::query()->create([
            'name' => 'Unverified Seeker',
            'email' => 'unverified@example.test',
        ]);
        $this->unverifiedUser->forceFill([
            'status' => 'active',
            'email_verified_at' => null,
        ])->save();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_requests_are_denied(): void
    {
        $this->getJson('/api/v1/me/saved-opportunities')->assertUnauthorized();
        $this->postJson('/api/v1/me/saved-opportunities', ['opportunity_id' => 1])->assertUnauthorized();
        $this->deleteJson('/api/v1/me/saved-opportunities/1')->assertUnauthorized();

        $this->getJson('/api/v1/me/applications')->assertUnauthorized();
        $this->postJson('/api/v1/me/applications', ['opportunity_id' => 1])->assertUnauthorized();
        $this->patchJson('/api/v1/me/applications/1', ['status' => 'applied'])->assertUnauthorized();
        $this->deleteJson('/api/v1/me/applications/1')->assertUnauthorized();
    }

    public function test_unverified_contact_denies_saving(): void
    {
        $opportunity = $this->createPublicOpportunity('valid-opp');

        $this->actingAs($this->unverifiedUser)
            ->postJson('/api/v1/me/saved-opportunities', [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Contact verification is required before saving opportunities.');

        $this->assertDatabaseCount('saved_opportunities', 0);
    }

    public function test_verified_user_can_save_and_it_is_idempotent(): void
    {
        $opportunity = $this->createPublicOpportunity('opp-1');

        // First save
        $this->actingAs($this->verifiedUser)
            ->postJson('/api/v1/me/saved-opportunities', [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.opportunity_id', $opportunity->id)
            ->assertJsonPath('data.is_available', true);

        $this->assertDatabaseCount('saved_opportunities', 1);

        // Second save (idempotent)
        $this->actingAs($this->verifiedUser)
            ->postJson('/api/v1/me/saved-opportunities', [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.opportunity_id', $opportunity->id);

        $this->assertDatabaseCount('saved_opportunities', 1);
    }

    public function test_saving_non_public_or_missing_opportunity_fails(): void
    {
        $this->actingAs($this->verifiedUser)
            ->postJson('/api/v1/me/saved-opportunities', [
                'opportunity_id' => 99999,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('saved_opportunities', 0);
    }

    public function test_user_can_list_and_remove_saved_opportunities(): void
    {
        $opp1 = $this->createPublicOpportunity('opp-1');
        $opp2 = $this->createPublicOpportunity('opp-2');

        $this->actingAs($this->verifiedUser);

        $this->postJson('/api/v1/me/saved-opportunities', ['opportunity_id' => $opp1->id])->assertCreated();
        $this->postJson('/api/v1/me/saved-opportunities', ['opportunity_id' => $opp2->id])->assertCreated();

        $response = $this->getJson('/api/v1/me/saved-opportunities')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->deleteJson('/api/v1/me/saved-opportunities/' . $opp1->id)
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->getJson('/api/v1/me/saved-opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.opportunity_id', $opp2->id);
    }

    public function test_saved_opportunities_are_scoped_to_the_authenticated_user(): void
    {
        $opp = $this->createPublicOpportunity('opp-scoped');

        $otherUser = User::query()->create([
            'name' => 'Other User',
            'email' => 'other@example.test',
        ]);
        $otherUser->forceFill([
            'status' => 'active',
            'email_verified_at' => $this->now,
        ])->save();

        $this->actingAs($this->verifiedUser)
            ->postJson('/api/v1/me/saved-opportunities', ['opportunity_id' => $opp->id])
            ->assertCreated();

        $this->actingAs($otherUser)
            ->getJson('/api/v1/me/saved-opportunities')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_application_tracker_crud_and_status_validation(): void
    {
        $opp = $this->createPublicOpportunity('opp-track');

        $this->actingAs($this->verifiedUser);

        // Create tracker entry with default 'interested'
        $createRes = $this->postJson('/api/v1/me/applications', [
            'opportunity_id' => $opp->id,
            'status' => 'interested',
            'notes' => 'Need to update CV.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'interested')
            ->assertJsonPath('data.status_source', 'user-reported')
            ->assertJsonPath('data.notes', 'Need to update CV.');

        $trackerId = $createRes->json('data.id');

        // Update status to 'applied'
        $this->patchJson('/api/v1/me/applications/' . $trackerId, [
            'status' => 'applied',
            'applied_at' => '2026-10-23T14:00:00Z',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.status_source', 'user-reported');

        // Invalid status rejected
        $this->patchJson('/api/v1/me/applications/' . $trackerId, [
            'status' => 'unsupported_status',
        ])->assertUnprocessable();

        // List
        $this->getJson('/api/v1/me/applications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trackerId)
            ->assertJsonPath('data.0.status', 'applied');

        // Delete
        $this->deleteJson('/api/v1/me/applications/' . $trackerId)
            ->assertOk();

        $this->getJson('/api/v1/me/applications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_tracker_records_are_strictly_owner_scoped(): void
    {
        $opp = $this->createPublicOpportunity('opp-private');

        $otherUser = User::query()->create([
            'name' => 'Attacker User',
            'email' => 'attacker@example.test',
        ]);
        $otherUser->forceFill([
            'status' => 'active',
            'email_verified_at' => $this->now,
        ])->save();

        $this->actingAs($this->verifiedUser);
        $createRes = $this->postJson('/api/v1/me/applications', [
            'opportunity_id' => $opp->id,
            'status' => 'interview',
            'notes' => 'Private interview notes',
        ])->assertCreated();

        $trackerId = $createRes->json('data.id');

        // Other user attempts to update or delete
        $this->actingAs($otherUser);

        $this->patchJson('/api/v1/me/applications/' . $trackerId, [
            'status' => 'applied',
        ])->assertNotFound();

        $this->deleteJson('/api/v1/me/applications/' . $trackerId)->assertOk();

        // Record still intact for the owner
        $this->actingAs($this->verifiedUser);
        $this->getJson('/api/v1/me/applications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private function createPublicOpportunity(string $slug): Opportunity
    {
        $opp = Opportunity::query()->create([
            'title' => 'Synthetic ' . $slug,
            'slug' => $slug,
            'summary' => 'Synthetic summary.',
            'description' => 'Synthetic full description.',
            'category_id' => $this->category->id,
            'submitted_by' => $this->staffReviewer->id,
            'organisation_name' => 'Synthetic Organisation',
            'status' => 'published',
            'published_at' => $this->now->subHour(),
        ]);

        DB::table('audit_logs')->insert([
            'actor_id' => $this->staffReviewer->id,
            'action' => 'opportunity.review.approved',
            'entity_type' => 'opportunity',
            'entity_id' => $opp->id,
            'metadata' => json_encode(['rationale' => 'Approved in test'], JSON_THROW_ON_ERROR),
            'created_at' => $this->now->subHour()->toDateTimeString(),
        ]);

        VerificationRecord::query()->create([
            'opportunity_id' => $opp->id,
            'reviewer_id' => $this->verifier->id,
            'result' => 'verified',
            'check_type' => 'source_details',
            'evidence_url' => 'https://example.test/evidence',
            'evidence_rationale' => 'Verified in test',
            'checked_at' => $this->now->subHour(),
            'created_at' => $this->now->subHour(),
        ]);

        return $opp;
    }
}
