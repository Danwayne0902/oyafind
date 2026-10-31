<?php

namespace App\Http\Controllers;

use App\Services\SeoMetadataService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the Vue shell with server-rendered metadata and a crawlable summary
 * for each public page. Unknown category or listing slugs return a real 404
 * instead of a soft 200, which is both correct for people and cleaner for
 * search engines.
 */
class PublicPageController extends Controller
{
    public function __construct(
        private readonly SeoMetadataService $seo,
    ) {}

    public function home(): Response
    {
        return response()->view('app', ['meta' => $this->seo->home()]);
    }

    public function categories(): Response
    {
        return response()->view('app', ['meta' => $this->seo->categories()]);
    }

    public function category(Request $request, string $slug): Response
    {
        $location = $request->query('location');
        $page = (int) $request->query('page', 1);

        $meta = $this->seo->category(
            $slug,
            is_string($location) ? $location : null,
            $page,
        );

        if ($meta === null) {
            return response()->view('app', ['meta' => $this->seo->notFound()], 404);
        }

        return response()->view('app', ['meta' => $meta]);
    }

    public function opportunity(string $slug): Response
    {
        $meta = $this->seo->opportunity($slug);

        if ($meta === null) {
            return response()->view('app', ['meta' => $this->seo->notFound()], 404);
        }

        return response()->view('app', ['meta' => $meta]);
    }

    public function page(string $page): Response
    {
        return response()->view('app', ['meta' => $this->seo->staticPage($page)]);
    }
}
