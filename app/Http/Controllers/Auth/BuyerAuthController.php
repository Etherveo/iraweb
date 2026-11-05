<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterBuyerRequest;
use App\Models\Developer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// Keputusan 2026-11-05: login disatukan — ONE login form at /masuk for
// all three actors, with an aktor switch (pembeli | admin_toko | developer).
// Tables and guards STAY separate: users (pembeli, admin_toko) via web,
// developers (role 'dev') via developer. Sessions do not cross guards.
// ACC-01 (register, pembeli-only), ACC-02/03/04 (unified login),
// ACC-06 (web logout), ACC-15 (simulation accounts log in as pembeli).
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

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('developer')->check()) {
            return redirect()->route('developer.dashboard');
        }

        if (Auth::guard('web')->check()) {
            return redirect($this->webHome());
        }

        $aktor = $request->query('aktor');
        if (! in_array($aktor, ['pembeli', 'admin_toko', 'developer'], true)) {
            $aktor = 'pembeli';
        }

        return view('auth.masuk', ['aktor' => $aktor]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $aktor = $request->validated('aktor');
        $credentials = $request->only('email', 'password');
        $generic = ['email' => 'Kredensial tidak valid.'];

        // Developer: separate table/guard, role 'dev'. Any failure —
        // unknown email, wrong password, or a web credential tried with
        // aktor=developer — gets the SAME generic message.
        if ($aktor === 'developer') {
            if (! Auth::guard('developer')->attempt($credentials)) {
                return back()->withErrors($generic)->withInput($request->only('email', 'aktor'));
            }

            $developer = Auth::guard('developer')->user();

            if (! $developer instanceof Developer || $developer->role !== 'dev') {
                Auth::guard('developer')->logout();

                return back()->withErrors($generic)->withInput($request->only('email', 'aktor'));
            }

            $request->session()->regenerate();

            return redirect()->intended(route('developer.dashboard'));
        }

        // Web actors (pembeli / admin_toko): attempt first, then check
        // that the account's role matches the selected aktor exactly.
        // Wrong-actor credentials are undone at once and get the SAME
        // generic message as a wrong password — never a hint about which
        // actor the email belongs to.
        if (! Auth::guard('web')->attempt($credentials)) {
            return back()->withErrors($generic)->withInput($request->only('email', 'aktor'));
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User || $user->role !== $aktor) {
            Auth::guard('web')->logout();

            return back()->withErrors($generic)->withInput($request->only('email', 'aktor'));
        }

        // ACC-07: inactive accounts get a clear (different) message.
        if ($user->is_active === false) {
            Auth::guard('web')->logout();

            return back()->withErrors(['email' => 'Akun Anda nonaktif. Silakan hubungi Admin Toko.'])->withInput($request->only('email', 'aktor'));
        }

        $request->session()->regenerate();

        return redirect()->intended($aktor === 'admin_toko' ? route('admin.dashboard') : route('katalog'));
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
