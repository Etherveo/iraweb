<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\Request;

// M1 ACC-08: unauthenticated users are redirected to the login form that
// matches the area they tried to open, not always to the buyer form.
class Authenticate extends BaseAuthenticate
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        if ($request->is('developer/*') || $request->is('developer')) {
            return route('developer.masuk');
        }

        if ($request->is('admin/*') || $request->is('admin')) {
            return route('admin.masuk');
        }

        return route('masuk');
    }
}
