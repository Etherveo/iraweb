<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// M1 ACC-08: server-side role check, never just a hidden menu.
// Runs after auth middleware. Wrong role on a clearly-owned route => 403
// (PRD §8: 403 or 404 per matrix; 403 is used here because the route
// itself is not a per-user resource whose existence must be hidden).
// Keputusan 2026-11-05: login disatukan — developer routes now declare
// role:dev as well (developers.role = 'dev'), so this middleware works
// for the developer guard too: Authenticate's shouldUse() makes
// $request->user() resolve to the developer-guard user on those routes.
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        // A user without a matching role must never pass, whatever guard.
        if (! isset($user->role) || ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
