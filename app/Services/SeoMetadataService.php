<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Location;
use App\Models\Opportunity;
use App\Repositories\OpportunityDiscoveryRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds the server-rendered head and the crawlable fallback for each public
 * page. The Vue app still owns the interactive experience; this layer makes
 * sure a crawler, a social preview or a browser with JavaScript disabled sees
 * the real title, description, canonical URL and listings for the page.
 */
class SeoMetadataService
{
    /**
     * ISO 3166-1 alpha-2 codes for the beyond-Nigeria countries in the
     * catalogue. Google Jobs validates addressCountry against this list, so a
     * missing entry is omitted rather than guessed.
     *
     * @var array<string, string>
     */
    private const COUNTRY_CODES = [
        'nigeria' => 'NG',
        'united-states' => 'US',
        'united-kingdom' => 'GB',
        'canada' => 'CA',
        'france' => 'FR',
        'belgium' => 'BE',
        'denmark' => 'DK',
        'qatar' => 'QA',
        'mauritius' => 'MU',
        'south-africa' => 'ZA',
        'kenya' => 'KE',
        'ethiopia' => 'ET',
        'ghana' => 'GH',
        'senegal' => 'SN',
        'egypt' => 'EG',
    ];

    public function __construct(
        private readonly DiscoveryService $discovery,
        private readonly OpportunityDiscoveryRepository $opportunities,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        $recent = $this->discovery->listPublicOpportunities([
            'per_page' => 12,
            'sort' => 'newest',
        ]);

        $categories = $this->discovery->listPublicCategories();

        return $this->page([
            'title' => 'OyaFind — Jobs, scholarships, grants and gigs in Nigeria',
            'description' => 'Find jobs, gigs, internships, scholarships, grants, training and tenders across Nigeria and beyond. Every listing shows when its source was last checked.',
            'canonical' => $this->url('/'),
            'jsonLd' => [
                $this->organisationSchema(),
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => 'OyaFind',
                    'alternateName' => 'OyaFind Nigeria',
                    'url' => $this->url('/'),
                    'description' => 'Opportunities for work, learning and business in Nigeria and beyond.',
                    'inLanguage' => 'en-NG',
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => $this->url('/search').'?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
            'fallback' => [
                'h1' => 'Find what’s next, closer to home.',
                'intro' => 'Jobs, scholarships, grants, training and gigs for people building a living anywhere in Nigeria and beyond. Every public listing records when its source and details were checked.',
                'links' => $categories
                    ->map(fn (Category $category): array => [
                        'label' => $category->name,
                        'url' => $this->url('/categories/'.$category->slug),
                    ])
                    ->all(),
                'items' => $this->fallbackItems($recent['data']),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function categories(): array
    {
        $categories = $this->discovery->listPublicCategories();

        return $this->page([
            'title' => 'Browse opportunity categories | OyaFind',
            'description' => 'Explore jobs, gigs, internships, scholarships, grants, training, business support and tenders. Filter every category by state or local government area.',
            'canonical' => $this->url('/categories'),
            'jsonLd' => [
                $this->organisationSchema(),
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => 'Opportunity categories on OyaFind',
                    'url' => $this->url('/categories'),
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'itemListElement' => $categories
                            ->values()
                            ->map(fn (Category $category, int $index): array => [
                                '@type' => 'ListItem',
                                'position' => $index + 1,
                                'name' => $category->name,
                                'url' => $this->url('/categories/'.$category->slug),
                            ])
                            ->all(),
                    ],
                ],
            ],
            'fallback' => [
                'h1' => 'Browse opportunities by category',
                'intro' => 'Every category is checked before a listing is published and can be filtered by state or local government area.',
                'links' => $categories
                    ->map(fn (Category $category): array => [
                        'label' => $category->name,
                        'url' => $this->url('/categories/'.$category->slug),
                    ])
                    ->all(),
                'items' => [],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function category(string $slug, ?string $locationSlug, int $page): ?array
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            return null;
        }

        $location = null;
        if (filled($locationSlug)) {
            $location = Location::query()
                ->where('slug', $locationSlug)
                ->where('is_active', true)
                ->first();
        }

        $filters = [
            'category' => $category->slug,
            'per_page' => 20,
            'page' => max(1, $page),
        ];

        if ($location !== null) {
            $filters['location'] = $location->slug;
        }

        $result = $this->discovery->listPublicOpportunities($filters);
        $heading = $location !== null
            ? $category->name.' in '.$location->name
            : $category->name.' in Nigeria';
        $total = (int) $result['total'];

        $canonical = $this->url('/categories/'.$category->slug);
        if ($location !== null) {
            $canonical .= '?location='.urlencode($location->slug);
        }

        $description = $total > 0
            ? Str::limit(
                'Browse '.$total.' source-checked '.Str::lower($category->name)
                .' listings '.($location !== null ? 'in '.$location->name : 'across Nigeria')
                .' and beyond. Filter by state or local government area and see when each source was last checked.',
                158,
            )
            : 'Browse '.Str::lower($category->name).' listings on OyaFind. Every published listing records when its source and details were checked.';

        $items = $result['data']->values();

        return $this->page([
            'title' => Str::limit($heading, 58, '').' | OyaFind',
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $page > 1 ? 'noindex,follow' : 'index,follow',
            'jsonLd' => [
                $this->organisationSchema(),
                $this->breadcrumbs([
                    ['name' => 'Home', 'url' => $this->url('/')],
                    ['name' => 'Categories', 'url' => $this->url('/categories')],
                    ['name' => $heading, 'url' => $canonical],
                ]),
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $heading,
                    'description' => $description,
                    'url' => $canonical,
                    'isPartOf' => ['@type' => 'WebSite', 'url' => $this->url('/')],
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'numberOfItems' => $total,
                        'itemListElement' => $items
                            ->take(20)
                            ->map(fn (Opportunity $opportunity, int $index): array => [
                                '@type' => 'ListItem',
                                'position' => $index + 1,
                                'name' => $opportunity->title,
                                'url' => $this->url('/opportunities/'.$opportunity->slug),
                            ])
                            ->all(),
                    ],
                ],
            ],
            'fallback' => [
                'h1' => $heading,
                'intro' => $description,
                'links' => array_merge(
                    $this->areaLinks($category),
                    $this->discovery->listPublicCategories()
                        ->reject(fn (Category $item): bool => $item->id === $category->id)
                        ->map(fn (Category $item): array => [
                            'label' => 'All '.$item->name,
                            'url' => $this->url('/categories/'.$item->slug),
                        ])
                        ->values()
                        ->all(),
                ),
                'items' => $this->fallbackItems($items),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function opportunity(string $slug): ?array
    {
        $opportunity = $this->discovery->findPublicOpportunity($slug);

        if ($opportunity === null) {
            return null;
        }

        $category = $opportunity->category;
        $location = $opportunity->location;
        $canonical = $this->url('/opportunities/'.$opportunity->slug);

        $description = Str::limit(
            trim(preg_replace('/\s+/', ' ', strip_tags((string) $opportunity->summary)) ?? ''),
            158,
        );

        if ($description === '') {
            $description = 'View this opportunity on OyaFind. The source and details were checked before it was published.';
        }

        $schemas = [
            $this->organisationSchema(),
            $this->breadcrumbs([
                ['name' => 'Home', 'url' => $this->url('/')],
                ['name' => $category->name, 'url' => $this->url('/categories/'.$category->slug)],
                ['name' => $opportunity->title, 'url' => $canonical],
            ]),
        ];

        $listingSchema = $this->listingSchema($opportunity);
        if ($listingSchema !== null) {
            $schemas[] = $listingSchema;
        }

        $links = [
            ['label' => 'All '.$category->name, 'url' => $this->url('/categories/'.$category->slug)],
        ];

        if ($location !== null) {
            $area = $this->areaForLocation($location);
            $links[] = [
                'label' => $category->name.' in '.$area->name,
                'url' => $this->url('/categories/'.$category->slug).'?location='.urlencode($area->slug),
            ];
        }

        $links[] = ['label' => 'Browse every category', 'url' => $this->url('/categories')];

        return $this->page([
            'title' => Str::limit($opportunity->title, 62, '').' | OyaFind',
            'description' => $description,
            'canonical' => $canonical,
            'type' => 'article',
            'jsonLd' => $schemas,
            'fallback' => [
                'h1' => $opportunity->title,
                'intro' => $description,
                'meta' => array_values(array_filter([
                    $opportunity->organisation_name,
                    $location?->name,
                    $opportunity->deadline_at?->format('j F Y') !== null
                        ? 'Deadline '.$opportunity->deadline_at->format('j F Y')
                        : null,
                ])),
                'links' => $links,
                'sourceUrl' => $opportunity->source_url ?: $opportunity->application_url,
                'items' => [],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function staticPage(string $key): array
    {
        if ($key === 'how-it-works') {
            return $this->page([
                'title' => 'How OyaFind checks every listing | OyaFind',
                'description' => 'See what OyaFind checks before publishing a listing, how source verification works, and why a check is not an endorsement or a guarantee.',
                'canonical' => $this->url('/how-it-works'),
                'jsonLd' => [
                    $this->organisationSchema(),
                    $this->breadcrumbs([
                        ['name' => 'Home', 'url' => $this->url('/')],
                        ['name' => 'How it works', 'url' => $this->url('/how-it-works')],
                    ]),
                ],
                'fallback' => [
                    'h1' => 'How OyaFind checks every listing',
                    'intro' => 'Every public listing has had its source and details checked. A check is not an endorsement, or a guarantee of safety, selection or payment.',
                    'links' => [],
                    'items' => [],
                ],
            ]);
        }

        if ($key === 'categories') {
            return $this->categories();
        }

        return $this->page([
            'title' => 'OyaFind',
            'description' => 'OyaFind shares public opportunities for work, learning and business in Nigeria and beyond.',
            'canonical' => $this->url('/'),
            'robots' => 'noindex,follow',
            'fallback' => [
                'h1' => 'OyaFind',
                'intro' => 'OyaFind shares public opportunities for work, learning and business in Nigeria and beyond.',
                'links' => [],
                'items' => [],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function notFound(): array
    {
        return $this->page([
            'title' => 'Page not found | OyaFind',
            'description' => 'This page could not be found. Browse jobs, scholarships, grants, training and gigs on OyaFind instead.',
            'canonical' => $this->url('/'),
            'robots' => 'noindex,follow',
            'fallback' => [
                'h1' => 'Page not found',
                'intro' => 'The page you are looking for has moved or no longer exists.',
                'links' => [
                    ['label' => 'Browse all categories', 'url' => $this->url('/categories')],
                    ['label' => 'Go to OyaFind home', 'url' => $this->url('/')],
                ],
                'items' => [],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function page(array $values): array
    {
        $page = array_merge([
            'title' => 'OyaFind',
            'description' => 'Opportunities for work, learning and business in Nigeria and beyond.',
            'canonical' => $this->url('/'),
            'robots' => 'index,follow',
            'type' => 'website',
            'image' => $this->url('/icons/oyafind-light-512.png'),
            'jsonLd' => [],
            'fallback' => [
                'h1' => 'OyaFind',
                'intro' => '',
                'links' => [],
                'items' => [],
            ],
        ], $values);

        $page['jsonLd'] = $this->withBaseSchemas((array) $page['jsonLd']);

        return $page;
    }

    /**
     * Every page carries the site identity, even when a caller forgot it.
     * Search engines use the Organization + WebSite pair to connect listings to
     * one publisher and to show the site name in results.
     *
     * @param  list<array<string, mixed>>  $schemas
     * @return list<array<string, mixed>>
     */
    private function withBaseSchemas(array $schemas): array
    {
        $types = array_map(
            static fn (array $schema): string => (string) ($schema['@type'] ?? ''),
            $schemas,
        );

        if (! in_array('Organization', $types, true)) {
            array_unshift($schemas, $this->organisationSchema());
        }

        if (! in_array('WebSite', $types, true)) {
            array_unshift($schemas, $this->websiteSchema());
        }

        return array_values($schemas);
    }

    /**
     * @param  iterable<int, Opportunity>  $opportunities
     * @return list<array<string, string>>
     */
    private function fallbackItems(iterable $opportunities): array
    {
        $items = [];

        foreach ($opportunities as $opportunity) {
            $items[] = [
                'title' => $opportunity->title,
                'url' => $this->url('/opportunities/'.$opportunity->slug),
                'meta' => trim(implode(' · ', array_filter([
                    $opportunity->organisation_name,
                    $opportunity->location?->name,
                    $opportunity->deadline_at?->format('j M Y'),
                ]))),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function listingSchema(Opportunity $opportunity): ?array
    {
        $category = $opportunity->category?->slug;
        $description = Str::limit(strip_tags((string) $opportunity->description), 5000, '');
        $location = $opportunity->location;
        $area = $location !== null ? $this->areaForLocation($location) : null;
        $isRemote = $location !== null
            && ($location->slug === 'remote' || $location->parent?->slug === 'remote');
        $countryCode = $this->countryCodeFor($location);
        $posted = $opportunity->published_at ?? $opportunity->created_at;

        $place = [
            '@type' => 'Place',
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $location?->name,
                'addressRegion' => $area !== null && $location !== null && $area->id !== $location->id
                    ? $area->name
                    : null,
                'addressCountry' => $countryCode,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];

        if ($category === 'jobs') {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'JobPosting',
                'title' => $opportunity->title,
                'description' => $description,
                'identifier' => [
                    '@type' => 'PropertyValue',
                    'name' => 'OyaFind',
                    'value' => (string) $opportunity->id,
                ],
                'datePosted' => $posted?->toIso8601String(),
                'validThrough' => $opportunity->deadline_at?->toIso8601String(),
                'employmentType' => $this->employmentType($opportunity),
                'hiringOrganization' => [
                    '@type' => 'Organization',
                    'name' => $opportunity->organisation_name,
                ],
                'jobLocation' => $isRemote ? null : $place,
                'jobLocationType' => $isRemote ? 'TELECOMMUTE' : null,
                'url' => $this->url('/opportunities/'.$opportunity->slug),
                'directApply' => false,
            ];

            return array_filter($schema, fn ($value): bool => $value !== null);
        }

        if ($category === 'grants') {
            return array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Grant',
                'name' => $opportunity->title,
                'description' => $description,
                'url' => $this->url('/opportunities/'.$opportunity->slug),
                'funder' => $opportunity->organisation_name
                    ? ['@type' => 'Organization', 'name' => $opportunity->organisation_name]
                    : null,
            ], fn ($value): bool => $value !== null);
        }

        if (in_array($category, ['internships', 'scholarships', 'training'], true)) {
            return array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'EducationalOccupationalProgram',
                'name' => $opportunity->title,
                'description' => $description,
                'url' => $this->url('/opportunities/'.$opportunity->slug),
                'programType' => $opportunity->opportunity_type,
                'provider' => $opportunity->organisation_name
                    ? ['@type' => 'Organization', 'name' => $opportunity->organisation_name]
                    : null,
            ], fn ($value): bool => $value !== null);
        }

        return null;
    }

    private function employmentType(Opportunity $opportunity): ?string
    {
        $type = Str::lower((string) $opportunity->opportunity_type);

        return match (true) {
            str_contains($type, 'part') => 'PART_TIME',
            str_contains($type, 'contract'), str_contains($type, 'freelance') => 'CONTRACTOR',
            str_contains($type, 'intern') => 'INTERN',
            str_contains($type, 'temporary') => 'TEMPORARY',
            str_contains($type, 'full') => 'FULL_TIME',
            default => null,
        };
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ],
                $items,
                array_keys($items),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organisationSchema(): array
    {
        $sameAs = array_values(array_filter(
            array_map(
                static fn (mixed $url): string => is_string($url) ? trim($url) : '',
                (array) config('oyafind.seo.same_as', []),
            ),
            static fn (string $url): bool => $url !== '',
        ));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'OyaFind',
            'url' => $this->url('/'),
            'logo' => $this->url('/icons/oyafind-light-512.png'),
            'description' => 'OyaFind shares source-checked opportunities for work, learning and business in Nigeria and beyond.',
            'areaServed' => [
                ['@type' => 'Country', 'name' => 'Nigeria'],
            ],
            'knowsLanguage' => 'en-NG',
            'sameAs' => $sameAs === [] ? null : $sameAs,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'OyaFind',
            'alternateName' => 'OyaFind Nigeria',
            'url' => $this->url('/'),
            'inLanguage' => 'en-NG',
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'OyaFind',
                'url' => $this->url('/'),
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->url('/search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * The area a listing belongs to for navigation: an LGA is grouped under
     * its state, everything else stands for itself. This keeps "Jobs in Lagos"
     * links pointing at the state rather than at the country or one LGA.
     */
    private function areaForLocation(Location $location): Location
    {
        if ($location->location_type === 'lga' && $location->parent !== null) {
            return $location->parent;
        }

        return $location;
    }

    /**
     * Crawlable links from a category page to the areas that actually hold
     * listings in that category. They reuse the public discovery query, so a
     * link can never advertise a withdrawn or unverified listing.
     *
     * @return list<array{label: string, url: string}>
     */
    private function areaLinks(Category $category, int $limit = 10): array
    {
        $query = $this->opportunities->publicListingQuery(['category' => $category->slug]);

        if ($query === null) {
            return [];
        }

        /** @var Collection<int, int> $counts */
        $counts = $query
            ->whereNotNull('location_id')
            ->select('location_id', DB::raw('count(*) as aggregate'))
            ->groupBy('location_id')
            ->pluck('aggregate', 'location_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $locations = Location::query()
            ->whereIn('id', $counts->keys()->all())
            ->with('parent')
            ->get()
            ->keyBy('id');

        $tallies = [];
        foreach ($counts as $locationId => $count) {
            $location = $locations->get((int) $locationId);
            if ($location === null) {
                continue;
            }

            $area = $this->areaForLocation($location);
            $tallies[$area->id] = ($tallies[$area->id] ?? 0) + (int) $count;
        }

        arsort($tallies);

        $areaIds = array_slice(array_keys($tallies), 0, $limit);
        $areas = Location::query()->whereIn('id', $areaIds)->get()->keyBy('id');

        $links = [];
        foreach ($areaIds as $areaId) {
            $area = $areas->get((int) $areaId);
            if ($area === null) {
                continue;
            }

            $links[] = [
                'label' => $category->name.' in '.$area->name,
                'url' => $this->url('/categories/'.$category->slug).'?location='.urlencode($area->slug),
            ];
        }

        return $links;
    }

    private function countryCodeFor(?Location $location): ?string
    {
        if ($location === null) {
            return null;
        }

        foreach ([$location, $location->parent] as $candidate) {
            if ($candidate !== null && isset(self::COUNTRY_CODES[$candidate->slug])) {
                return self::COUNTRY_CODES[$candidate->slug];
            }
        }

        return null;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
