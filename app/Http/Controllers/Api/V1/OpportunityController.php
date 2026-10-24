<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpportunityIndexRequest;
use App\Http\Resources\OpportunityResource;
use App\Services\DiscoveryService;
use Illuminate\Http\JsonResponse;

class OpportunityController extends Controller
{
    public function index(
        OpportunityIndexRequest $request,
        DiscoveryService $discovery,
    ): JsonResponse {
        $result = $discovery->listPublicOpportunities($request->validated());

        return response()->json([
            'data' => $result['data']
                ->map(fn ($opportunity): array => OpportunityResource::make($opportunity)->resolve($request))
                ->all(),
            'meta' => [
                'current_page' => $result['current_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'last_page' => $result['last_page'],
            ],
        ]);
    }

    public function show(string $slug, DiscoveryService $discovery): JsonResponse
    {
        $opportunity = $discovery->findPublicOpportunity($slug);
        if ($opportunity === null) {
            return response()->json([
                'message' => 'Opportunity not found.',
                'errors' => ['opportunity' => ['Opportunity not found.']],
            ], 404);
        }

        return response()->json([
            'data' => OpportunityResource::make($opportunity)->resolve(request()),
        ]);
    }
}
