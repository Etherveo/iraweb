<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// ACC-03: separate admin form, only role admin_toko. Buyer credentials
// here get the same generic message as a wrong password.
class AdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        if ($user instanceof User && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.admin-masuk');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::guard('web')->attempt($request->only('email', 'password'))) {
            return back()->withErrors(['email' => 'Kredensial tidak valid.'])->withInput($request->only('email'));
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            Auth::guard('web')->logout();

            return back()->withErrors(['email' => 'Kredensial tidak valid.'])->withInput($request->only('email'));
        }

        if ($user->is_active === false) {
            Auth::guard('web')->logout();

            return back()->withErrors(['email' => 'Akun Anda nonaktif. Silakan hubungi Admin Toko.'])->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }
}
