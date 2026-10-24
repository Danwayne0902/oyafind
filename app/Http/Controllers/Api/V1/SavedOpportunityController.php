<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SavedOpportunityResource;
use App\Services\SavedOpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SavedOpportunityController extends Controller
{
    public function index(
        Request $request,
        SavedOpportunityService $service,
    ): AnonymousResourceCollection {
        $saved = $service->listSaved($request->user('web'));

        return SavedOpportunityResource::collection($saved);
    }

    public function store(
        Request $request,
        SavedOpportunityService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'opportunity_id' => ['required', 'integer'],
        ]);

        $record = $service->save(
            $request->user('web'),
            (int) $validated['opportunity_id'],
        );

        return (new SavedOpportunityResource(
            $record->load(['opportunity.category', 'opportunity.location.parent'])
        ))->response()->setStatusCode(201);
    }

    public function destroy(
        Request $request,
        int|string $opportunityId,
        SavedOpportunityService $service,
    ): JsonResponse {
        $service->unsave($request->user('web'), (int) $opportunityId);

        return response()->json(['status' => 'ok']);
    }
}

