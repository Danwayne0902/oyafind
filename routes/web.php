<?php

use App\Http\Controllers\Admin\OpportunityController as AdminOpportunityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::post('/admin/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('admin.logout');

/*
 * Staff area. These routes render Blade views rather than the Vue shell so
 * moderation does not depend on the public bundle, and every action is a
 * normal form post with CSRF protection and session authentication.
 */
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminOpportunityController::class, 'index'])->name('dashboard');
    Route::get('/opportunities', [AdminOpportunityController::class, 'index'])->name('opportunities.index');
    Route::get('/opportunities/create', [AdminOpportunityController::class, 'create'])->name('opportunities.create');
    Route::post('/opportunities', [AdminOpportunityController::class, 'store'])->name('opportunities.store');
    Route::get('/opportunities/{opportunity}/edit', [AdminOpportunityController::class, 'edit'])->name('opportunities.edit');
    Route::put('/opportunities/{opportunity}', [AdminOpportunityController::class, 'update'])->name('opportunities.update');
    Route::delete('/opportunities/{opportunity}', [AdminOpportunityController::class, 'destroy'])->name('opportunities.destroy');
    Route::post('/opportunities/{opportunity}/review', [AdminOpportunityController::class, 'review'])->name('opportunities.review');
    Route::post('/opportunities/{opportunity}/publish', [AdminOpportunityController::class, 'publish'])->name('opportunities.publish');
    Route::post('/opportunities/{opportunity}/withdraw', [AdminOpportunityController::class, 'withdraw'])->name('opportunities.withdraw');
});

Route::get('/', function () {
    return view('app');
});

Route::view('/search', 'app');
Route::view('/categories', 'app');
Route::view('/how-it-works', 'app');
Route::view('/register', 'app');
Route::view('/login', 'app');
Route::view('/profile', 'app');
Route::view('/saved', 'app');
Route::view('/tracker', 'app');
Route::view('/my-opportunities', 'app');
Route::view('/verify-email/{user}/{hash}', 'app')->name('email-verification.page');
Route::view('/categories/{slug}', 'app')->where('slug', '[A-Za-z0-9-]+');
Route::view('/opportunities/{slug}', 'app')->where('slug', '[A-Za-z0-9-]+');
