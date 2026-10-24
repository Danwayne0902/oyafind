<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Services\DiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request, DiscoveryService $discovery): JsonResponse
    {
        return response()->json([
            'data' => $discovery->listPublicCategories()
                ->map(fn ($category): array => CategoryResource::make($category)->resolve($request))
                ->all(),
        ]);
    }
}
