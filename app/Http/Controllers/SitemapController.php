<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Location;
use App\Repositories\OpportunityDiscoveryRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * The sitemap is generated from the same evidence-backed discovery query that
 * powers the public feed, so it can never advertise a withdrawn, expired or
 * unverified listing. Category and location combinations are included only
 * when at least one live listing uses them.
 */
class SitemapController extends Controller
{
    private const MAX_URLS = 50000;

    public function __invoke(OpportunityDiscoveryRepository $opportunities): Response
    {
        $urls = [
            ['loc' => $this->url('/'), 'lastmod' => null],
            ['loc' => $this->url('/categories'), 'lastmod' => null],
            ['loc' => $this->url('/how-it-works'), 'lastmod' => null],
        ];

        $categoryLastmod = [];
        $categoryLocationLastmod = [];

        foreach (Category::query()->where('is_active', true)->orderBy('sort_order')->get() as $category) {
            $categoryLastmod[$category->id] = null;
            $urls[] = [
                'loc' => $this->url('/categories/'.$category->slug),
                'lastmod' => null,
            ];
        }

        $query = $opportunities->publicListingQuery([]);

        if ($query !== null && count($urls) < self::MAX_URLS) {
            $query
                ->select(['id', 'slug', 'category_id', 'location_id', 'published_at', 'updated_at'])
                ->orderBy('id')
                ->chunk(500, function ($listings) use (&$urls, &$categoryLastmod, &$categoryLocationLastmod): void {
                    foreach ($listings as $listing) {
                        if (count($urls) >= self::MAX_URLS) {
                            return;
                        }

                        $lastmod = $this->lastmod($listing->published_at ?? $listing->updated_at);
                        $urls[] = [
                            'loc' => $this->url('/opportunities/'.$listing->slug),
                            'lastmod' => $lastmod,
                        ];

                        if ($listing->category_id !== null) {
                            $categoryLastmod[$listing->category_id] = $this->newest(
                                $categoryLastmod[$listing->category_id] ?? null,
                                $lastmod,
                            );
                        }

                        if ($listing->category_id !== null && $listing->location_id !== null) {
                            $key = $listing->category_id.':'.$listing->location_id;
                            $categoryLocationLastmod[$key] = $this->newest(
                                $categoryLocationLastmod[$key] ?? null,
                                $lastmod,
                            );
                        }
                    }
                });
        }

        // Write category lastmod values back now that the listings are known.
        foreach ($urls as $index => $url) {
            if ($url['lastmod'] !== null || ! str_contains($url['loc'], '/categories/')) {
                continue;
            }

            $slug = basename(parse_url($url['loc'], PHP_URL_PATH) ?? '');
            $category = Category::query()->where('slug', $slug)->first();

            if ($category !== null && isset($categoryLastmod[$category->id])) {
                $urls[$index]['lastmod'] = $categoryLastmod[$category->id];
            }
        }

        if ($categoryLocationLastmod !== []) {
            $categorySlugs = Category::query()->pluck('slug', 'id');
            $locations = Location::query()
                ->whereIn('id', array_map(
                    fn (string $key): int => (int) explode(':', $key)[1],
                    array_keys($categoryLocationLastmod),
                ))
                ->get(['id', 'slug', 'parent_id'])
                ->keyBy('id');

            $locationParents = Location::query()
                ->whereIn('id', $locations->pluck('parent_id')->filter()->unique()->all())
                ->pluck('slug', 'id');

            foreach ($categoryLocationLastmod as $key => $lastmod) {
                [$categoryId, $locationId] = array_map('intval', explode(':', $key));

                if (! isset($categorySlugs[$categoryId]) || ! isset($locations[$locationId])) {
                    continue;
                }

                if (count($urls) >= self::MAX_URLS) {
                    break;
                }

                $urls[] = [
                    'loc' => $this->url('/categories/'.$categorySlugs[$categoryId])
                        .'?location='.urlencode($locations[$locationId]->slug),
                    'lastmod' => $lastmod,
                ];

                $parentSlug = $locations[$locationId]->parent_id
                    ? ($locationParents[$locations[$locationId]->parent_id] ?? null)
                    : null;

                if ($parentSlug !== null && $parentSlug !== $locations[$locationId]->slug && count($urls) < self::MAX_URLS) {
                    $urls[] = [
                        'loc' => $this->url('/categories/'.$categorySlugs[$categoryId])
                            .'?location='.urlencode($parentSlug),
                        'lastmod' => $lastmod,
                    ];
                }
            }
        }

        // A category/location combination can be reached through several
        // listings, so collapse those to one address and keep the newest
        // lastmod. Duplicate <loc> entries add no value to a crawler.
        $deduped = [];
        foreach ($urls as $url) {
            $loc = $url['loc'];
            if (! isset($deduped[$loc])) {
                $deduped[$loc] = $url;

                continue;
            }

            $deduped[$loc]['lastmod'] = $this->newest($deduped[$loc]['lastmod'], $url['lastmod']);
        }

        return response()
            ->view('sitemap', ['urls' => array_values($deduped)])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=1800');
    }

    private function lastmod(?Carbon $date): ?string
    {
        return $date?->toDateString();
    }

    private function newest(?string $current, ?string $candidate): ?string
    {
        if ($candidate === null) {
            return $current;
        }

        return $current === null || $candidate > $current ? $candidate : $current;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
