<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// M1 ACC-08: server-side role check, never just a hidden menu.
// Runs after auth middleware. Wrong role on a clearly-owned route => 403
// (PRD §8: 403 or 404 per matrix; 403 is used here because the route
// itself is not a per-user resource whose existence must be hidden).
// Developer routes do not use this middleware: the developer guard alone
// is the role check there (ACC-10).
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        // Developer model has no role column; it must never pass a web-role check.
        if (! isset($user->role) || ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
