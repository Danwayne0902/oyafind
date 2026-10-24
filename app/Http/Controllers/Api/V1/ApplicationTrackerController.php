<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationTrackerResource;
use App\Services\ApplicationTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ApplicationTrackerController extends Controller
{
    public function index(
        Request $request,
        ApplicationTrackerService $service,
    ): AnonymousResourceCollection {
        $applications = $service->listApplications($request->user('web'));

        return ApplicationTrackerResource::collection($applications);
    }

    public function store(
        Request $request,
        ApplicationTrackerService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'opportunity_id' => ['required', 'integer'],
            'status' => ['nullable', 'string', Rule::in(ApplicationTrackerService::ALLOWED_STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'applied_at' => ['nullable', 'date'],
        ]);

        $record = $service->track(
            $request->user('web'),
            (int) $validated['opportunity_id'],
            $validated,
        );

        return (new ApplicationTrackerResource(
            $record->load(['opportunity.category', 'opportunity.location.parent'])
        ))->response()->setStatusCode(201);
    }

    public function update(
        Request $request,
        int|string $id,
        ApplicationTrackerService $service,
    ): ApplicationTrackerResource {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(ApplicationTrackerService::ALLOWED_STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'applied_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $record = $service->update(
            $request->user('web'),
            (int) $id,
            $validated,
        );

        return new ApplicationTrackerResource($record);
    }

    public function destroy(
        Request $request,
        int|string $id,
        ApplicationTrackerService $service,
    ): JsonResponse {
        $service->delete($request->user('web'), (int) $id);

        return response()->json(['status' => 'ok']);
    }
}

