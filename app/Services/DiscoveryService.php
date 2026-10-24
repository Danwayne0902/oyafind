<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Opportunity;
use App\Repositories\OpportunityDiscoveryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DiscoveryService
{
    public function __construct(
        private readonly OpportunityDiscoveryRepository $opportunities,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   data: Collection<int, Opportunity>,
     *   current_page: int,
     *   per_page: int,
     *   total: int,
     *   last_page: int
     * }
     */
    public function listPublicOpportunities(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);
        $query = $this->opportunities->publicListingQuery($filters);

        if ($query === null) {
            $paginator = new LengthAwarePaginator(collect(), 0, $perPage, $page);
        } else {
            match ($filters['sort'] ?? 'newest') {
                'deadline' => $query
                    ->orderByRaw('CASE WHEN deadline_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('deadline_at')
                    ->orderByDesc('id'),
                'title' => $query->orderBy('title')->orderBy('id'),
                default => $query->orderByDesc('published_at')->orderByDesc('id'),
            };

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        }

        return [
            'data' => $paginator->getCollection(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function findPublicOpportunity(string $slug): ?Opportunity
    {
        return $this->opportunities->findPublicBySlug($slug);
    }

    /**
     * Categories are public taxonomy, not evidence of an opportunity's status.
     *
     * @return Collection<int, Category>
     */
    public function listPublicCategories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'description', 'icon_key']);
    }
}
