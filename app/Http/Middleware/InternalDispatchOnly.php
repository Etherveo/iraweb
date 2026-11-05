<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// M3b, blueprint §8.4. Guards the internal simulator gateway
// (POST /api/v1/simulator/payments). Only the InternalDispatcher can
// set the `internal_dispatch` request attribute; a real external HTTP
// request can never set it, so it gets a 404 here.
class InternalDispatchOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('internal_dispatch') !== true) {
            abort(404);
        }

        return $next($request);
    }
}
