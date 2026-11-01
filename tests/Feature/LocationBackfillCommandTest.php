<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_reviewed_locations_from_an_evidence_csv(): void
    {
        $user = User::query()->create(['name' => 'Synthetic submitter']);
        $category = Category::query()->create([
            'name' => 'Jobs',
            'slug' => 'jobs',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $location = Location::query()->create([
            'name' => 'Synthetic Oyo',
            'slug' => 'synthetic-oyo',
            'location_type' => 'state',
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $user->id,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);

        $opportunity = Opportunity::query()->create([
            'title' => 'Synthetic listing',
            'slug' => 'synthetic-listing',
            'summary' => 'Synthetic summary.',
            'description' => 'Synthetic description.',
            'category_id' => $category->id,
            'submitted_by' => $user->id,
            'organisation_name' => 'Synthetic organisation',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'locations');
        file_put_contents($path, "slug,location_slug,evidence\nsynthetic-listing,synthetic-oyo,\"Source label: Ibadan, Oyo\"\n");

        $this->artisan('locations:backfill', ['file' => $path])
            ->assertSuccessful();

        unlink($path);

        $this->assertSame($location->id, $opportunity->fresh()->location_id);
    }

    public function test_it_rejects_an_unreviewed_location_before_writing_anything(): void
    {
        $user = User::query()->create(['name' => 'Synthetic submitter']);
        $category = Category::query()->create([
            'name' => 'Jobs',
            'slug' => 'jobs',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        Location::query()->create([
            'name' => 'Synthetic unreviewed',
            'slug' => 'synthetic-unreviewed',
            'location_type' => 'state',
            'is_active' => false,
            'catalogue_origin' => 'oyafind_reviewed',
        ]);

        $opportunity = Opportunity::query()->create([
            'title' => 'Synthetic listing',
            'slug' => 'synthetic-listing',
            'summary' => 'Synthetic summary.',
            'description' => 'Synthetic description.',
            'category_id' => $category->id,
            'submitted_by' => $user->id,
            'organisation_name' => 'Synthetic organisation',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'locations');
        file_put_contents($path, "slug,location_slug,evidence\nsynthetic-listing,synthetic-unreviewed,\"No review provenance\"\n");

        $this->artisan('locations:backfill', ['file' => $path])
            ->assertFailed();

        unlink($path);

        $this->assertNull($opportunity->fresh()->location_id);
    }
}
