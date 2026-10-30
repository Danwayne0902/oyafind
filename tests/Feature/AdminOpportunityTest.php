<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOpportunityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@example.test', 'admin');
        $this->verifier = $this->makeUser('verifier@example.test', 'admin');

        config([
            'oyafind.policies.account_registration' => true,
            'oyafind.discovery.enabled' => true,
            'oyafind.discovery.staff_review_action' => 'staff_approved_listing',
            'oyafind.discovery.staff_reviewer_ids' => [$this->admin->id],
            'oyafind.discovery.staff_reviewer_roles' => ['admin'],
            'oyafind.discovery.verification.reviewer_ids' => [$this->verifier->id],
            'oyafind.discovery.verification.reviewer_roles' => ['admin'],
            'oyafind.discovery.verification.allowed_check_types' => ['source_live'],
            'oyafind.discovery.verification.required_check_types' => ['source_live'],
            'oyafind.discovery.verification.require_distinct_reviewers' => true,
        ]);

        Category::query()->create([
            'name' => 'Jobs',
            'slug' => 'jobs',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_guests_and_non_admins_cannot_reach_the_admin_area(): void
    {
        $this->get('/admin')->assertRedirect();

        $member = $this->makeUser('member@example.test', 'user');
        $this->actingAs($member)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_create_edit_and_delete_a_draft_listing(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/opportunities', $this->payload())
            ->assertRedirect();

        $opportunity = Opportunity::query()->firstOrFail();
        $this->assertSame('draft', $opportunity->status);
        $this->assertSame($this->admin->id, $opportunity->submitted_by);
        $this->assertNotNull($opportunity->deadline_at);

        $this->actingAs($this->admin)
            ->put("/admin/opportunities/{$opportunity->id}", $this->payload(['title' => 'Updated title']))
            ->assertRedirect();

        $this->assertSame('Updated title', $opportunity->fresh()->title);

        $this->actingAs($this->admin)
            ->delete("/admin/opportunities/{$opportunity->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('opportunities', ['id' => $opportunity->id]);
    }

    public function test_review_and_publish_follow_the_evidence_policy(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/opportunities', $this->payload())
            ->assertRedirect();

        $opportunity = Opportunity::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post("/admin/opportunities/{$opportunity->id}/review", [
                'evidence_reference' => 'https://example.com/jobs/1',
                'rationale' => 'Title, organisation and deadline match the source.',
            ])
            ->assertRedirect();

        $this->assertSame('reviewed', $opportunity->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'opportunity',
            'entity_id' => $opportunity->id,
            'action' => 'staff_approved_listing',
            'actor_id' => $this->admin->id,
        ]);

        // The reviewer cannot publish their own approval.
        $this->actingAs($this->admin)
            ->post("/admin/opportunities/{$opportunity->id}/publish")
            ->assertSessionHasErrors('publish');

        $this->actingAs($this->verifier)
            ->post("/admin/opportunities/{$opportunity->id}/publish")
            ->assertRedirect();

        $opportunity->refresh();
        $this->assertSame('published', $opportunity->status);
        $this->assertSame($this->verifier->id, $opportunity->verified_by);
        $this->assertDatabaseHas('opportunity_verifications', [
            'opportunity_id' => $opportunity->id,
            'reviewer_id' => $this->verifier->id,
            'check_type' => 'source_live',
            'result' => 'verified',
        ]);

        $this->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $opportunity->slug);
    }

    public function test_publishing_requires_a_source_link(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/opportunities', $this->payload(['source_url' => null]))
            ->assertRedirect();

        $opportunity = Opportunity::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post("/admin/opportunities/{$opportunity->id}/review", [
                'rationale' => 'Checked the printed flyer.',
            ])
            ->assertRedirect();

        $this->actingAs($this->verifier)
            ->post("/admin/opportunities/{$opportunity->id}/publish")
            ->assertSessionHasErrors('publish');

        $this->assertSame('reviewed', $opportunity->fresh()->status);
    }

    private function makeUser(string $email, string $role): User
    {
        $user = new User([
            'name' => 'Synthetic '.$role,
            'email' => $email,
            'password' => 'correct horse battery staple',
        ]);
        $user->forceFill(['role' => $role, 'status' => 'active'])->save();

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Graduate trainee programme',
            'summary' => 'A paid twelve-month graduate programme in Lagos.',
            'description' => 'A short original description with a link to the source listing.',
            'category_id' => Category::query()->value('id'),
            'location_id' => null,
            'organisation_name' => 'Example Group',
            'source_url' => 'https://example.com/jobs/1',
            'application_url' => null,
            'deadline_at' => null,
            'duration_days' => 30,
            'work_mode' => 'onsite',
            'currency' => 'NGN',
        ], $overrides);
    }
}
