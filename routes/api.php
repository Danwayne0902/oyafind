<?php

use App\Http\Controllers\Api\V1\ApplicationTrackerController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\SavedOpportunityController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json(['status' => 'ok']))
    ->name('api.v1.health');

Route::get('/v1/locations', [LocationController::class, 'index'])
    ->name('api.v1.locations.index');

Route::get('/v1/opportunities', [OpportunityController::class, 'index'])
    ->name('api.v1.opportunities.index');

Route::get('/v1/opportunities/{slug}', [OpportunityController::class, 'show'])
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('api.v1.opportunities.show');

Route::get('/v1/categories', [CategoryController::class, 'index'])
    ->name('api.v1.categories.index');

// These routes opt into Laravel's session and CSRF middleware while remaining
// under the versioned API namespace and JSON response conventions.
Route::middleware('web')->prefix('/v1')->group(function (): void {
    Route::get('/auth/csrf-token', [AuthController::class, 'csrfToken'])
        ->name('api.v1.auth.csrf-token');
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1')
        ->name('api.v1.auth.register');
    Route::get('/auth/registration-status', [AuthController::class, 'registrationStatus'])
        ->name('api.v1.auth.registration-status');
    Route::get('/auth/verify-email/{user}/{hash}', [AuthController::class, 'verificationLink'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('api.v1.auth.verify-email');
    Route::post('/auth/verify-email/{user}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.v1.auth.verify-email.confirm');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.v1.auth.login');
    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:web')
        ->name('api.v1.auth.logout');

    Route::middleware('auth:web')->group(function (): void {
        Route::get('/me', [MeController::class, 'show'])->name('api.v1.me.show');
        Route::patch('/me', [MeController::class, 'update'])->name('api.v1.me.update');

        Route::get('/me/saved-opportunities', [SavedOpportunityController::class, 'index'])
            ->name('api.v1.me.saved-opportunities.index');
        Route::post('/me/saved-opportunities', [SavedOpportunityController::class, 'store'])
            ->name('api.v1.me.saved-opportunities.store');
        Route::delete('/me/saved-opportunities/{opportunityId}', [SavedOpportunityController::class, 'destroy'])
            ->name('api.v1.me.saved-opportunities.destroy');

        Route::get('/me/applications', [ApplicationTrackerController::class, 'index'])
            ->name('api.v1.me.applications.index');
        Route::post('/me/applications', [ApplicationTrackerController::class, 'store'])
            ->name('api.v1.me.applications.store');
        Route::patch('/me/applications/{id}', [ApplicationTrackerController::class, 'update'])
            ->name('api.v1.me.applications.update');
        Route::delete('/me/applications/{id}', [ApplicationTrackerController::class, 'destroy'])
            ->name('api.v1.me.applications.destroy');
    });
});
