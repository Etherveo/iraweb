<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// M1 ACC-07: status is checked on every authenticated request, not only
// at login. An account deactivated mid-session becomes invalid on the
// next request: its web guard is logged out and it is sent back to the
// matching login form with a clear message. Developer accounts have no
// is_active column (blueprint §6.2), so they are skipped here.
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_active === false) {
            $guard = $this->guardFor($request);
            Auth::guard($guard)->logout();
            $request->session()->regenerate();

            $login = $request->is('admin/*') || $request->is('admin')
                ? route('admin.masuk')
                : route('masuk');

            return redirect($login)->withErrors([
                'email' => 'Akun Anda nonaktif. Silakan hubungi Admin Toko.',
            ]);
        }

        return $next($request);
    }

    private function guardFor(Request $request): string
    {
        if ($request->is('developer/*') || $request->is('developer')) {
            return 'developer';
        }

        return 'web';
    }
}
