<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterBuyerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// ACC-01 (register), ACC-02 (buyer login), ACC-06 (web logout), ACC-15
// (simulation accounts log in here as normal buyers; their origin stays
// developer_simulation, only their normal-UI activity is origin=normal, M3+).
class BuyerAuthController extends Controller
{
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect($this->webHome());
        }

        return view('auth.daftar');
    }

    public function register(RegisterBuyerRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => 'pembeli',
            'origin' => 'normal',
            'is_active' => true,
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // ACC-01: straight to katalog (M2 placeholder in M1).
        return redirect()->route('katalog');
    }

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect($this->webHome());
        }

        return view('auth.masuk');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('web')->attempt($credentials)) {
            return back()->withErrors(['email' => 'Kredensial tidak valid.'])->withInput($request->only('email'));
        }

        $user = Auth::guard('web')->user();

        // ACC-02: admin/developer credentials on this form are rejected
        // with the SAME generic message. Admin accounts live in users too,
        // so a successful attempt for a non-pembeli must be undone at once.
        // ACC-07: inactive accounts get a clear (different) message.
        if (! $user instanceof User || ! $user->isPembeli()) {
            Auth::guard('web')->logout();

            return back()->withErrors(['email' => 'Kredensial tidak valid.'])->withInput($request->only('email'));
        }

        if ($user->is_active === false) {
            Auth::guard('web')->logout();

            return back()->withErrors(['email' => 'Akun Anda nonaktif. Silakan hubungi Admin Toko.'])->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('katalog'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // ACC-06: log out only the web guard. Do NOT invalidate the whole
        // session (that would also kill a developer-guard login sharing
        // the same session store); regenerating the session id retires
        // the old session while keeping the other guard intact.
        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk');
    }

    private function webHome(): string
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User && $user->isAdmin() ? route('admin.dashboard') : route('katalog');
    }
}
