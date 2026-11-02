<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API clients do not always send an Accept header. Without JSON negotiation
 * Laravel turns a validation failure into a redirect, which hides the real
 * error from a crawler or a plain fetch() call. Every /api route answers in
 * JSON, including its errors.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
