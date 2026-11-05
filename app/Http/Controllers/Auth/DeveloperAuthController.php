<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Keputusan 2026-11-05: login disatukan — this controller no longer owns
// a login form or a POST login endpoint. Login for all actors, including
// developers, lives in BuyerAuthController via the unified /masuk form.
// Only the per-guard developer logout remains here (ACC-06): it touches
// the developer guard only and returns to the unified form with the
// developer actor pre-selected.
class DeveloperAuthController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('developer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk', ['aktor' => 'developer']);
    }
}
