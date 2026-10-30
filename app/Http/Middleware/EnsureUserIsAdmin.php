<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the staff area to active administrator accounts. Broken or
 * unverified sessions are sent back through the normal sign-in flow.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest('/login?returnTo='.urlencode($request->getRequestUri()));
        }

        if ($user->status !== 'active' || $user->role !== 'admin') {
            abort(403, 'This area is restricted to OyaFind staff.');
        }

        return $next($request);
    }
}
