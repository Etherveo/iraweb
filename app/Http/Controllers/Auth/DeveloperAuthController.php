<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// ACC-04: developer guard/table, fully separate from web. Web credentials
// simply do not exist in the developers provider, so they fail with the
// same generic message. Logout here only touches the developer guard.
class DeveloperAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('developer')->check()) {
            return redirect()->route('developer.dashboard');
        }

        return view('auth.developer-masuk');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::guard('developer')->attempt($request->only('email', 'password'))) {
            return back()->withErrors(['email' => 'Kredensial tidak valid.'])->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('developer.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('developer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('developer.masuk');
    }
}
