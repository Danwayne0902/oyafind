<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationCatalogueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unset_location_policy_returns_empty_catalogue_without_seeded_records(): void
    {
        config(['oyafind.policies.location_catalogue' => null]);

        $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.nextCursor', null)
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, public');

        $this->assertSame(0, Location::query()->count());
    }

    public function test_enabled_but_empty_catalogue_returns_a_stable_version_and_cache_headers(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);

        $first = $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertHeader('Cache-Control', 'max-age=60, public, stale-while-revalidate=300');

        $second = $this->getJson('/api/v1/locations')->assertOk();

        $this->assertSame(
            $first->headers->get('X-Catalogue-Version'),
            $second->headers->get('X-Catalogue-Version'),
        );
        $this->assertSame($first->headers->get('ETag'), $second->headers->get('ETag'));
        $this->assertSame(0, Location::query()->count());
    }

    public function test_only_active_human_reviewed_locations_are_returned_with_public_parent_info(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);
        $reviewer = User::query()->create(['name' => 'Synthetic Reviewer']);

        $parent = $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Parent',
            'slug' => 'synthetic-parent',
            'location_type' => 'test-parent',
            'source_name' => 'Private source name',
            'source_reference' => 'private-source-reference',
            'source_version' => 'private-source-version',
            'source_retrieved_at' => '2026-10-21',
        ]);
        $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Child',
            'slug' => 'synthetic-child',
            'location_type' => 'test-child',
            'parent_id' => $parent->id,
        ]);
        Location::query()->create([
            'name' => 'Synthetic Inactive',
            'slug' => 'synthetic-inactive',
            'is_active' => false,
        ]);

        DB::table('locations')->insert([
            'name' => 'Synthetic Legacy Unreviewed',
            'slug' => 'synthetic-legacy-unreviewed',
            'is_active' => true,
            'catalogue_origin' => 'synthetic-fixture',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('locations')->insert([
            'name' => 'Synthetic Blank Origin',
            'slug' => 'synthetic-blank-origin',
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => ' ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/locations')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $response->assertJsonPath('data.1.parent.id', $parent->id);
        $response->assertJsonPath('data.1.parent.name', 'Synthetic Parent');
        $response->assertJsonMissing(['name' => 'Synthetic Inactive']);
        $response->assertJsonMissing(['name' => 'Synthetic Legacy Unreviewed']);
        $response->assertJsonMissing(['name' => 'Synthetic Blank Origin']);
    }

    public function test_search_and_cursor_pagination_are_bounded_and_deterministic(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);
        $reviewer = User::query()->create(['name' => 'Synthetic Reviewer']);
        $this->makeReviewedLocation($reviewer, ['name' => 'Synthetic Alpha', 'slug' => 'synthetic-alpha']);
        $this->makeReviewedLocation($reviewer, ['name' => 'Synthetic Beta', 'slug' => 'synthetic-beta']);
        $this->makeReviewedLocation($reviewer, ['name' => 'Other Fixture', 'slug' => 'other-fixture']);

        $firstPage = $this->getJson('/api/v1/locations?q=Synthetic&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Synthetic Alpha');
        $cursor = $firstPage->json('meta.nextCursor');

        $this->assertNotNull($cursor);

        $this->getJson('/api/v1/locations?q=Synthetic&limit=1&cursor='.$cursor)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Synthetic Beta')
            ->assertJsonPath('meta.nextCursor', null);
    }

    public function test_invalid_search_pagination_and_unrecognized_filters_return_validation_errors(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);

        $this->getJson('/api/v1/locations?q='.str_repeat('x', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
        $this->getJson('/api/v1/locations?limit=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
        $this->getJson('/api/v1/locations?limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
        $this->getJson('/api/v1/locations?cursor=not-a-cursor')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cursor');
        $this->getJson('/api/v1/locations?is_active=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_active');
    }

    public function test_location_catalogue_has_no_public_write_route(): void
    {
        $this->postJson('/api/v1/locations')->assertMethodNotAllowed();
    }

    public function test_public_projection_contains_only_allowlisted_location_and_parent_fields(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);
        $reviewer = User::query()->create(['name' => 'Synthetic Reviewer']);
        $location = $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Public Fixture',
            'slug' => 'synthetic-public-fixture',
            'source_name' => 'Secret source name',
            'source_reference' => 'secret-reference',
            'source_version' => 'secret-version',
            'source_retrieved_at' => '2026-10-21',
        ]);

        $response = $this->getJson('/api/v1/locations')->assertOk();

        $this->assertSame(
            ['id', 'name', 'slug', 'location_type', 'parent'],
            array_keys($response->json('data.0')),
        );
        $response->assertJsonPath('data.0.id', $location->id);
        $response->assertJsonMissingPath('data.0.reviewed_by');
        $response->assertJsonMissingPath('data.0.reviewed_at');
        $response->assertJsonMissingPath('data.0.catalogue_origin');
        $response->assertJsonMissingPath('data.0.source_name');
        $response->assertJsonMissingPath('data.0.source_reference');
        $response->assertJsonMissingPath('data.0.source_version');
        $response->assertJsonMissingPath('data.0.source_retrieved_at');
        $response->assertDontSee('Secret source name');
        $response->assertDontSee('secret-reference');
    }

    public function test_etag_is_stable_for_unchanged_catalogue_and_changes_with_public_content(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);
        $reviewer = User::query()->create(['name' => 'Synthetic Reviewer']);
        $location = $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Original',
            'slug' => 'synthetic-original',
        ]);

        $first = $this->getJson('/api/v1/locations')->assertOk();
        $unchanged = $this->getJson('/api/v1/locations')->assertOk();

        $this->assertSame($first->headers->get('ETag'), $unchanged->headers->get('ETag'));
        $this->assertSame(
            $first->headers->get('X-Catalogue-Version'),
            $unchanged->headers->get('X-Catalogue-Version'),
        );

        $location->update(['name' => 'Synthetic Renamed']);
        $changed = $this->getJson('/api/v1/locations')->assertOk();

        $this->assertNotSame($first->headers->get('ETag'), $changed->headers->get('ETag'));
        $this->assertNotSame(
            $first->headers->get('X-Catalogue-Version'),
            $changed->headers->get('X-Catalogue-Version'),
        );
    }

    public function test_catalogue_version_changes_when_a_visible_parent_relationship_changes(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);
        $reviewer = User::query()->create(['name' => 'Synthetic Reviewer']);
        $parent = $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Parent',
            'slug' => 'synthetic-parent-for-hierarchy',
        ]);
        $child = $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Child',
            'slug' => 'synthetic-child-for-hierarchy',
        ]);

        $before = $this->getJson('/api/v1/locations')->assertOk();
        $child->update(['parent_id' => $parent->id]);
        $after = $this->getJson('/api/v1/locations')->assertOk();

        $this->assertNotSame(
            $before->headers->get('X-Catalogue-Version'),
            $after->headers->get('X-Catalogue-Version'),
        );
    }

    public function test_matching_if_none_match_returns_304_with_cache_validators(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);
        $reviewer = User::query()->create(['name' => 'Synthetic Reviewer']);
        $this->makeReviewedLocation($reviewer);

        $initial = $this->getJson('/api/v1/locations')->assertOk();
        $etag = $initial->headers->get('ETag');

        $this->withHeader('If-None-Match', $etag)
            ->get('/api/v1/locations')
            ->assertNotModified()
            ->assertHeader('ETag', $etag)
            ->assertHeader('Cache-Control', 'max-age=60, public, stale-while-revalidate=300')
            ->assertHeader('X-Catalogue-Version', $initial->headers->get('X-Catalogue-Version'));
    }

    public function test_location_seeder_is_empty_by_default_and_accepts_only_reviewed_configured_records(): void
    {
        $reviewer = User::query()->create(['name' => 'Synthetic Reviewer']);
        config([
            'oyafind.policies.location_catalogue' => null,
            'oyafind.location_catalogue_seed_records' => [[
                'name' => 'Synthetic Gated Fixture',
                'slug' => 'synthetic-gated-fixture',
                'location_type' => 'test-area',
                'is_active' => true,
                'reviewed_at' => now()->toDateTimeString(),
                'reviewed_by' => $reviewer->id,
                'catalogue_origin' => 'oyafind_reviewed',
            ]],
        ]);

        $this->seed(LocationSeeder::class);
        $this->assertSame(0, Location::query()->count());

        config(['oyafind.policies.location_catalogue' => true]);
        $this->seed(LocationSeeder::class);

        $this->assertDatabaseHas('locations', [
            'slug' => 'synthetic-gated-fixture',
            'is_active' => true,
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);
    }

    public function test_location_seeder_rejects_a_configured_record_without_review_provenance(): void
    {
        config([
            'oyafind.policies.location_catalogue' => true,
            'oyafind.location_catalogue_seed_records' => [[
                'name' => 'Synthetic Unreviewed Seed',
                'slug' => 'synthetic-unreviewed-seed',
                'is_active' => true,
                'catalogue_origin' => 'oyafind_reviewed',
            ]],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->seed(LocationSeeder::class);
    }

    public function test_type_and_parent_filters_keep_states_and_lgas_in_separate_levels(): void
    {
        config(['oyafind.policies.location_catalogue' => true]);
        $reviewer = User::query()->create(['name' => 'Synthetic Reviewer']);

        $oyo = $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Oyo',
            'slug' => 'synthetic-oyo',
            'location_type' => 'state',
        ]);
        $lagos = $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Lagos',
            'slug' => 'synthetic-lagos',
            'location_type' => 'state',
        ]);

        $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Ibadan North',
            'slug' => 'synthetic-ibadan-north',
            'location_type' => 'lga',
            'parent_id' => $oyo->id,
        ]);
        $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Ibadan South',
            'slug' => 'synthetic-ibadan-south',
            'location_type' => 'lga',
            'parent_id' => $oyo->id,
        ]);
        $this->makeReviewedLocation($reviewer, [
            'name' => 'Synthetic Ikeja',
            'slug' => 'synthetic-ikeja',
            'location_type' => 'lga',
            'parent_id' => $lagos->id,
        ]);

        $states = $this->getJson('/api/v1/locations?type=state')->assertOk();
        $this->assertSame(
            ['Synthetic Lagos', 'Synthetic Oyo'],
            collect($states->json('data'))->pluck('name')->sort()->values()->all(),
        );
        $this->assertSame(
            ['state'],
            collect($states->json('data'))->pluck('location_type')->unique()->values()->all(),
        );

        $oyoChildren = $this->getJson('/api/v1/locations?type=lga&parent=synthetic-oyo')->assertOk();
        $this->assertSame(
            ['Synthetic Ibadan North', 'Synthetic Ibadan South'],
            collect($oyoChildren->json('data'))->pluck('name')->sort()->values()->all(),
        );
        $this->assertSame(
            ['synthetic-oyo'],
            collect($oyoChildren->json('data'))
                ->pluck('parent.slug')
                ->unique()
                ->values()
                ->all(),
        );

        $this->getJson('/api/v1/locations?type=planet')->assertStatus(422);
        $this->getJson('/api/v1/locations?parent=not a slug')->assertStatus(422);
    }

    private function makeReviewedLocation(User $reviewer, array $overrides = []): Location
    {
        return Location::query()->create(array_merge([
            'name' => 'Synthetic Reviewed Fixture',
            'slug' => 'synthetic-reviewed-fixture',
            'location_type' => 'test-area',
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ], $overrides));
    }
}
