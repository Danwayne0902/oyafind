<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

/**
 * The reviewed source registry.
 *
 * Every source is a candidate surface, never a publication decision. Operators
 * can disable any entry without losing the items already staged from it.
 */
class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'name' => 'Jobzilla',
                'slug' => 'jobzilla',
                'homepage_url' => 'https://www.jobzilla.ng',
                'feed_url' => 'https://www.jobzilla.ng/feed',
                'adapter' => 'rss',
                'default_category_slug' => 'jobs',
                'timezone' => 'Africa/Lagos',
                'attribution' => 'Jobzilla',
                'terms_url' => null,
            ],
            [
                'name' => 'Opportunities for Africans',
                'slug' => 'opportunities-for-africans',
                'homepage_url' => 'https://www.opportunitiesforafricans.com',
                'feed_url' => 'https://www.opportunitiesforafricans.com/feed/',
                'adapter' => 'rss',
                'default_category_slug' => 'scholarships',
                'timezone' => 'UTC',
                'attribution' => 'Opportunities for Africans',
                'terms_url' => null,
            ],
            [
                'name' => 'Opportunity Desk',
                'slug' => 'opportunity-desk',
                'homepage_url' => 'https://www.opportunitydesk.org',
                'feed_url' => 'https://www.opportunitydesk.org/feed/',
                'adapter' => 'rss',
                'default_category_slug' => 'scholarships',
                'timezone' => 'UTC',
                'attribution' => 'Opportunity Desk',
                'terms_url' => null,
            ],
            [
                'name' => 'Funds for NGOs',
                'slug' => 'funds-for-ngos',
                'homepage_url' => 'https://www.fundsforngos.org',
                'feed_url' => 'https://www.fundsforngos.org/feed/',
                'adapter' => 'rss',
                'default_category_slug' => 'grants',
                'timezone' => 'UTC',
                'attribution' => 'FundsforNGOs',
                'terms_url' => null,
            ],
            [
                'name' => 'World Bank procurement',
                'slug' => 'world-bank-procurement',
                'homepage_url' => 'https://projects.worldbank.org',
                'feed_url' => 'https://search.worldbank.org/api/v2/procnotices?format=json&qterm=Nigeria',
                'adapter' => 'world_bank',
                'default_category_slug' => 'tenders',
                'timezone' => 'UTC',
                'attribution' => 'World Bank Group',
                'terms_url' => 'https://www.worldbank.org/en/about/legal/terms-and-conditions',
            ],
        ];

        foreach ($sources as $source) {
            Source::query()->firstOrCreate(
                ['slug' => $source['slug']],
                [...$source, 'enabled' => true],
            );
        }
    }
}
