<?php

use App\Http\Controllers\Admin\OpportunityController as AdminOpportunityController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\SitemapController;
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

Route::get('/', [PublicPageController::class, 'home'])->name('home');

/*
 * Short category links. The category pages live at /categories/{slug}, so a
 * typed or shared /jobs lands on the canonical URL instead of a 404. These
 * redirect rather than render so search engines index one address per page.
 */
foreach ([
    'jobs', 'gigs', 'internships', 'scholarships',
    'training', 'grants', 'business-support', 'tenders',
] as $categorySlug) {
    Route::redirect('/'.$categorySlug, '/categories/'.$categorySlug, 301);
}

// Staff sign in through the same screen as everyone else.
Route::redirect('/admin/login', '/login', 302);

Route::get('/search', fn () => app(PublicPageController::class)->page('search'));
Route::get('/categories', [PublicPageController::class, 'categories'])->name('categories.index');
Route::get('/how-it-works', fn () => app(PublicPageController::class)->page('how-it-works'))->name('how-it-works');
Route::get('/register', fn () => app(PublicPageController::class)->page('register'))->name('register');
Route::get('/login', fn () => app(PublicPageController::class)->page('login'))->name('login');
Route::get('/profile', fn () => app(PublicPageController::class)->page('profile'))->name('profile');
Route::get('/saved', fn () => app(PublicPageController::class)->page('saved'))->name('saved');
Route::get('/tracker', fn () => app(PublicPageController::class)->page('tracker'))->name('tracker');
Route::get('/my-opportunities', fn () => app(PublicPageController::class)->page('my-opportunities'))->name('my-opportunities');
Route::get('/verify-email/{user}/{hash}', fn () => app(PublicPageController::class)->page('verify-email'))->name('email-verification.page');
Route::get('/categories/{slug}', [PublicPageController::class, 'category'])->where('slug', '[A-Za-z0-9-]+')->name('categories.show');
Route::get('/opportunities/{slug}', [PublicPageController::class, 'opportunity'])->where('slug', '[A-Za-z0-9-]+')->name('opportunities.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
