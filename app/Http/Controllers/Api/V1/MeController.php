<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(
        Request $request,
        AccountAuthenticationService $authentication,
    ): JsonResponse {
        return response()->json([
            'data' => $authentication->profile($request->user('web')),
        ]);
    }

    public function update(
        ProfileUpdateRequest $request,
        AccountAuthenticationService $authentication,
    ): JsonResponse {
        $user = $request->user('web');
        $user->fill($request->validated())->save();

        return response()->json([
            'data' => $authentication->profile($user->fresh()),
        ]);
    }
}
