<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocationIndexRequest;
use App\Services\LocationCatalogueService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(
        LocationIndexRequest $request,
        LocationCatalogueService $catalogue,
    ): JsonResponse {
        $filters = $request->validated();
        $result = $catalogue->searchReviewedLocations(
            $filters['q'] ?? null,
            (int) ($filters['limit'] ?? 20),
            isset($filters['cursor']) ? (int) $filters['cursor'] : null,
            $filters['type'] ?? null,
            $filters['parent'] ?? null,
        );

        $body = [
            'data' => $result['data'],
            'meta' => [
                'catalogueVersion' => $result['catalogueVersion'],
                'nextCursor' => $result['nextCursor'],
            ],
        ];

        $enabled = config('oyafind.policies.location_catalogue') === true;
        $cacheControl = $enabled
            ? 'public, max-age=60, stale-while-revalidate=300'
            : 'public, max-age=0, must-revalidate';

        $response = response()->json($body)
            ->header('Cache-Control', $cacheControl)
            ->header('X-Catalogue-Version', $result['catalogueVersion']);

        $etag = hash(
            'sha256',
            json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }
}
