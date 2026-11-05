<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\Request;

// M1 ACC-08, Keputusan 2026-11-05: login disatukan — unauthenticated
// users are redirected to the ONE login form at /masuk, with the actor
// pre-selected via ?aktor=, not to separate forms.
class Authenticate extends BaseAuthenticate
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        if ($request->is('developer/*') || $request->is('developer')) {
            return route('masuk', ['aktor' => 'developer']);
        }

        if ($request->is('admin/*') || $request->is('admin')) {
            return route('masuk', ['aktor' => 'admin_toko']);
        }

        return route('masuk');
    }
}
