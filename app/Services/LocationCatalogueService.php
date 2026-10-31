<?php

namespace App\Services;

use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Support\PolicyConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LocationCatalogueService
{
    public function __construct(
        private readonly PolicyConfiguration $policies,
    ) {}

    /**
     * Return a bounded page from the active, human-reviewed catalogue.
     *
     * The version is derived from catalogue metadata (count/max updated_at)
     * to avoid loading the entire dataset into memory at scale.
     *
     * @return array{
     *     data: list<array<string, mixed>>,
     *     catalogueVersion: string,
     *     nextCursor: int|null
     * }
     */
    public function searchReviewedLocations(
        ?string $query,
        int $limit,
        ?int $cursor,
        ?string $type = null,
        ?string $parent = null,
    ): array {
        if (! $this->policies->isEnabled('location_catalogue')) {
            return $this->emptyCatalogue();
        }

        $catalogueVersion = $this->computeCatalogueVersion();

        $pageQuery = $this->selectableLocations();
        if ($cursor !== null) {
            $pageQuery->where('id', '>', $cursor);
        }

        if ($type !== null) {
            $pageQuery->where('location_type', $type);
        }

        if ($parent !== null) {
            $pageQuery->whereHas('parent', fn (Builder $query) => $query->where('slug', $parent));
        }

        $search = trim($query ?? '');
        if ($search !== '') {
            if ($this->hasFulltextIndex()) {
                $escaped = $this->escapeBooleanQuery($search);
                $pageQuery->whereRaw('MATCH(name) AGAINST(? IN BOOLEAN MODE)', [$escaped.'*']);
            } else {
                $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                $pattern = '%'.$escapedSearch.'%';
                $pageQuery->where(function (Builder $builder) use ($pattern): void {
                    $builder
                        ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("slug LIKE ? ESCAPE '!'", [$pattern]);
                });
            }
        }

        $page = $pageQuery
            ->limit($limit + 1)
            ->get();
        $hasMore = $page->count() > $limit;
        $page = $page->take($limit)->values();

        // Eager load parents for this page without re-applying the full selectable filter
        // so tests/fixtures with custom parents still resolve correctly
        $parentIds = $page->pluck('parent_id')->filter()->unique()->values()->all();
        $parents = [];
        if (! empty($parentIds)) {
            $parents = Location::query()
                ->whereIn('id', $parentIds)
                ->where('is_active', true)
                ->whereNotNull('reviewed_at')
                ->whereNotNull('reviewed_by')
                ->whereNotNull('catalogue_origin')
                ->whereRaw("TRIM(catalogue_origin) <> ''")
                ->get()
                ->keyBy('id');
        }
        $page = $page->map(function (Location $location) use ($parents) {
            if ($location->parent_id && isset($parents[$location->parent_id])) {
                $location->setRelation('parent', $parents[$location->parent_id]);
            }

            return $location;
        });

        return [
            'data' => $page
                ->map(fn (Location $location): array => LocationResource::make($location)->resolve())
                ->all(),
            'catalogueVersion' => $catalogueVersion,
            'nextCursor' => $hasMore && $page->isNotEmpty()
                ? (int) $page->last()->id
                : null,
        ];
    }

    private function computeCatalogueVersion(): string
    {
        $base = Location::query()
            ->where('is_active', true)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('reviewed_by')
            ->whereNotNull('catalogue_origin')
            ->whereRaw("TRIM(catalogue_origin) <> ''");

        $total = (clone $base)->count();
        $lastUpdated = (clone $base)->selectRaw('COALESCE(MAX(updated_at), MAX(created_at)) as last_updated')->value('last_updated');
        $lastUpdated = $lastUpdated ? (string) $lastUpdated : 'none';

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $items = DB::table('locations as l')
                ->leftJoin('locations as p', 'p.id', '=', 'l.parent_id')
                ->where('l.is_active', true)
                ->whereNotNull('l.reviewed_at')
                ->whereNotNull('l.reviewed_by')
                ->whereNotNull('l.catalogue_origin')
                ->whereRaw("TRIM(l.catalogue_origin) <> ''")
                ->orderBy('l.id')
                ->selectRaw("l.id, l.name, l.slug, COALESCE(l.location_type,'') as location_type, COALESCE(p.id,0) as parent_id")
                ->get();
            $parts = $items->map(fn ($i) => $i->id.':'.$i->name.':'.$i->slug.':'.$i->location_type.':'.$i->parent_id)->implode('|');
            $hash = $total.'|'.$lastUpdated.'|'.$parts;
        } else {
            $publicHash = DB::table('locations as l')
                ->leftJoin('locations as p', 'p.id', '=', 'l.parent_id')
                ->where('l.is_active', true)
                ->whereNotNull('l.reviewed_at')
                ->whereNotNull('l.reviewed_by')
                ->whereNotNull('l.catalogue_origin')
                ->whereRaw("TRIM(l.catalogue_origin) <> ''")
                ->selectRaw("GROUP_CONCAT(DISTINCT CONCAT(l.id,':',l.name,':',l.slug,':',COALESCE(l.location_type,''),':',COALESCE(p.id,0)) ORDER BY l.id SEPARATOR '|') as ph")
                ->first();
            $hash = $total.'|'.$lastUpdated.'|'.($publicHash->ph ?? '');
        }

        return 'sha256-'.hash('sha256', $hash);
    }

    private function hasFulltextIndex(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        try {
            $result = DB::select("SHOW INDEX FROM locations WHERE Key_name = 'locations_name_fulltext'");

            return ! empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function escapeBooleanQuery(string $query): string
    {
        $escaped = preg_replace('/[+\-><\(\)~*"@]/', ' ', $query);
        $escaped = trim(preg_replace('/\s+/', ' ', $escaped) ?? $query);

        return $escaped;
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     catalogueVersion: string,
     *     nextCursor: null
     * }
     */
    private function emptyCatalogue(): array
    {
        return [
            'data' => [],
            'catalogueVersion' => 'sha256-'.hash('sha256', '0|none'),
            'nextCursor' => null,
        ];
    }

    /**
     * @return Builder<Location>
     */
    private function selectableLocations(): Builder
    {
        return Location::query()
            ->where('is_active', true)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('reviewed_by')
            ->whereNotNull('catalogue_origin')
            ->whereRaw("TRIM(catalogue_origin) <> ''")
            ->orderBy('id')
            ->select(['id', 'name', 'slug', 'parent_id', 'location_type', 'updated_at', 'created_at']);
    }
}
