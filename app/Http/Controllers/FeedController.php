<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Repositories\OpportunityDiscoveryRepository;
use Illuminate\Http\Response;

/**
 * A small RSS feed of the newest public listings.
 *
 * It gives aggregators and readers a lightweight surface that always matches
 * the public feed: withdrawn, expired and unverified listings never appear
 * because the same discovery query builds it.
 */
class FeedController extends Controller
{
    public function __invoke(OpportunityDiscoveryRepository $opportunities): Response
    {
        $query = $opportunities->publicListingQuery([]);

        $listings = $query === null
            ? collect()
            : $query->orderByDesc('published_at')->orderByDesc('id')->limit(50)->get();

        return response()
            ->view('feed', [
                'listings' => $listings,
                'updatedAt' => $listings->first()?->published_at,
                'siteUrl' => rtrim((string) config('app.url'), '/'),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=900');
    }
}
