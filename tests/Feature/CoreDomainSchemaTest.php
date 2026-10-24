<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\OpportunityReport;
use App\Models\SavedOpportunity;
use App\Models\User;
use App\Models\VerificationRecord;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class CoreDomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_core_domain_tables_and_utc_timestamps(): void
    {
        foreach ([
            'users',
            'locations',
            'categories',
            'opportunities',
            'saved_opportunities',
            'user_applications',
            'opportunity_reports',
            'opportunity_verifications',
            'notifications',
            'audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected [{$table}] table.");
        }

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('+00:00', config('database.connections.mysql.timezone'));
        $this->assertTrue(Schema::hasColumns('locations', [
            'reviewed_at',
            'reviewed_by',
            'is_active',
            'catalogue_origin',
            'source_name',
            'source_reference',
            'source_version',
            'source_retrieved_at',
        ]));
    }

    public function test_category_seeder_creates_only_the_eight_confirmed_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame([
            'business-support',
            'gigs',
            'grants',
            'internships',
            'jobs',
            'scholarships',
            'tenders',
            'training',
        ], Category::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(8, Category::query()->count());
        $this->assertSame(0, Location::query()->count(), 'No geography is seeded by the category seeder.');
    }

    public function test_profile_and_opportunity_location_references_are_optional(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::query()->create(['name' => 'Controlled Test User']);
        $category = Category::query()->where('slug', 'jobs')->firstOrFail();

        $opportunity = $this->makeOpportunity($user, $category);

        $this->assertNull($user->fresh()->home_location_id);
        $this->assertNull($opportunity->location_id);
    }

    public function test_database_enforces_unique_slugs_and_user_owned_save_uniqueness(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::query()->create(['name' => 'Controlled Test User']);
        $category = Category::query()->where('slug', 'jobs')->firstOrFail();
        $opportunity = $this->makeOpportunity($user, $category);

        $this->expectException(QueryException::class);
        SavedOpportunity::query()->create([
            'user_id' => $user->id,
            'opportunity_id' => $opportunity->id,
        ]);
        SavedOpportunity::query()->create([
            'user_id' => $user->id,
            'opportunity_id' => $opportunity->id,
        ]);
    }

    public function test_location_activation_requires_human_review_and_provenance_is_complete_when_present(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Location::query()->create([
            'name' => 'Synthetic Unreviewed Area',
            'slug' => 'synthetic-unreviewed-area',
            'location_type' => 'test-area',
            'is_active' => true,
        ]);
    }

    public function test_source_derived_locations_require_all_provenance_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Location::query()->create([
            'name' => 'Synthetic Provenance Fixture',
            'slug' => 'synthetic-provenance-fixture',
            'catalogue_origin' => 'osm_derived',
            'source_reference' => 'fixture-reference',
            'source_version' => 'fixture-snapshot',
            'source_retrieved_at' => '2026-10-22',
        ]);
    }

    public function test_reviewed_location_with_complete_provenance_can_be_saved_without_being_seeded(): void
    {
        $reviewer = User::query()->create(['name' => 'Controlled Reviewer']);
        $location = Location::query()->create([
            'name' => 'Synthetic Reviewed Fixture',
            'slug' => 'synthetic-reviewed-fixture',
            'location_type' => 'test-area',
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'catalogue_origin' => 'controlled-test-fixture',
            'source_name' => 'Controlled test source',
            'source_reference' => 'fixture-reference',
            'source_version' => 'fixture-snapshot',
            'source_retrieved_at' => '2026-10-22',
        ]);

        $this->assertSame($reviewer->id, $location->reviewed_by);
        $this->assertSame('fixture-reference', $location->source_reference);
        $this->assertSame(1, Location::query()->count());
    }

    public function test_unapproved_status_values_are_not_restricted_to_proposed_enums(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::query()->create(['name' => 'Controlled Test User']);
        $category = Category::query()->where('slug', 'jobs')->firstOrFail();

        $opportunity = $this->makeOpportunity($user, $category, [
            'status' => 'owner-configured-publication-value',
            'verification_status' => 'owner-configured-trust-value',
            'application_method' => 'owner-configured-application-value',
        ]);

        DB::table('user_applications')->insert([
            'user_id' => $user->id,
            'opportunity_id' => $opportunity->id,
            'status' => 'owner-configured-tracker-value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('owner-configured-publication-value', $opportunity->fresh()->status);
        $this->assertDatabaseHas('user_applications', [
            'user_id' => $user->id,
            'status' => 'owner-configured-tracker-value',
        ]);
    }

    public function test_reports_can_be_created_anonymously(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::query()->create(['name' => 'Controlled Test User']);
        $category = Category::query()->where('slug', 'jobs')->firstOrFail();
        $opportunity = $this->makeOpportunity($user, $category);

        $report = OpportunityReport::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => null,
            'reason' => 'owner-configured-report-reason',
            'details' => 'Synthetic anonymous report fixture.',
        ]);

        $this->assertNull($report->user_id);
        $this->assertDatabaseHas('opportunity_reports', [
            'id' => $report->id,
            'opportunity_id' => $opportunity->id,
        ]);
    }

    public function test_verification_records_require_an_evidence_reference_or_rationale(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::query()->create(['name' => 'Controlled Test User']);
        $category = Category::query()->where('slug', 'jobs')->firstOrFail();
        $opportunity = $this->makeOpportunity($user, $category);

        $this->expectException(InvalidArgumentException::class);
        VerificationRecord::query()->create([
            'opportunity_id' => $opportunity->id,
            'reviewer_id' => $user->id,
            'result' => 'owner-configured-result',
            'check_type' => 'owner-configured-check',
            'checked_at' => now(),
        ]);
    }

    private function makeOpportunity(User $user, Category $category, array $overrides = []): Opportunity
    {
        return Opportunity::query()->create(array_merge([
            'title' => 'Controlled Test Opportunity',
            'slug' => 'controlled-test-opportunity',
            'summary' => 'Synthetic fixture for schema tests.',
            'description' => 'No real opportunity or geography is represented.',
            'category_id' => $category->id,
            'submitted_by' => $user->id,
            'organisation_name' => 'Test Fixture',
        ], $overrides));
    }
}
