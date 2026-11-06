<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Ira Stationery' }}</title>
    {{-- Same conditional as the stock welcome view: only use @vite when a
         build manifest (or hot file) exists, so tests without a Vite build
         do not fail. Run `npm run build` after adding frontend packages. --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- Manrope + Inter (UI.md §3), dibundel lewat vite.config.js. --}}
        @fonts
    @endif
</head>
@php($isAdminShell = auth('web')->check() && auth('web')->user()->isAdmin())
<body @class(['has-admin-sidebar' => $isAdminShell])>
@if ($isAdminShell)
    <header class="admin-mobile-bar">
        <div class="admin-brand">
            <span class="admin-brand-mark" aria-hidden="true">i.</span>
            <div class="admin-brand-meta">
                <strong>Ira Stationery</strong>
                <span>Admin Toko</span>
            </div>
        </div>
        <button
            type="button"
            class="admin-hamburger"
            data-admin-drawer-open
            aria-controls="admin-sidebar"
            aria-expanded="false"
            aria-label="Buka menu navigasi"
        >
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
        </button>
    </header>

    <div class="admin-sidebar-backdrop" data-admin-drawer-close hidden></div>

    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigasi Admin">
        <div class="admin-sidebar-head">
            <div class="admin-brand">
                <span class="admin-brand-mark" aria-hidden="true">i.</span>
                <div class="admin-brand-meta">
                    <strong>Ira Stationery</strong>
                    <span>Admin Toko</span>
                </div>
            </div>
            <button type="button" class="admin-drawer-close" data-admin-drawer-close aria-label="Tutup menu navigasi">×</button>
        </div>

        <nav class="admin-sidebar-nav" aria-label="Menu Admin">
            <div class="admin-nav-group">
                <p class="admin-nav-heading">ANALITIK</p>
                <a href="{{ route('admin.dashboard') }}" @class(['admin-nav-item', 'is-active' => request()->routeIs('admin.dashboard')]) @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <span>Dashboard</span>
                </a>
                <span class="admin-nav-item is-disabled" aria-disabled="true">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                    <span>Tren</span>
                </span>
                <span class="admin-nav-item is-disabled" aria-disabled="true">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    <span>Funnel</span>
                </span>
                <span class="admin-nav-item is-disabled" aria-disabled="true">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/></svg>
                    <span>Perbandingan</span>
                </span>
                <span class="admin-nav-item is-disabled" aria-disabled="true">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                    <span>Aktivitas &amp; Minat</span>
                </span>
                <span class="admin-nav-item is-disabled" aria-disabled="true">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                    <span>Kategori</span>
                </span>
            </div>

            <div class="admin-nav-group">
                <p class="admin-nav-heading">TOKO</p>
                <a href="{{ route('admin.produk.index') }}" @class(['admin-nav-item', 'is-active' => request()->routeIs('admin.produk.*')]) @if (request()->routeIs('admin.produk.*')) aria-current="page" @endif>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    <span>Produk</span>
                </a>
                <a href="{{ route('admin.kategori.index') }}" @class(['admin-nav-item', 'is-active' => request()->routeIs('admin.kategori.*')]) @if (request()->routeIs('admin.kategori.*')) aria-current="page" @endif>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/></svg>
                    <span>Kategori produk</span>
                </a>
            </div>

            <div class="admin-nav-group">
                <p class="admin-nav-heading">TRANSAKSI</p>
                <a href="{{ route('admin.pesanan.index') }}" @class(['admin-nav-item', 'is-active' => request()->routeIs('admin.pesanan.*')]) @if (request()->routeIs('admin.pesanan.*')) aria-current="page" @endif>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                    <span>Pesanan</span>
                </a>
                <a href="{{ route('admin.pembayaran.index') }}" @class(['admin-nav-item', 'is-active' => request()->routeIs('admin.pembayaran.*')]) @if (request()->routeIs('admin.pembayaran.*')) aria-current="page" @endif>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                    <span>Pembayaran</span>
                </a>
            </div>

            <div class="admin-nav-group">
                <p class="admin-nav-heading">AKUN</p>
                <a href="{{ route('admin.akun-pembeli.index') }}" @class(['admin-nav-item', 'is-active' => request()->routeIs('admin.akun-pembeli.*')]) @if (request()->routeIs('admin.akun-pembeli.*')) aria-current="page" @endif>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Akun pembeli</span>
                </a>
            </div>
        </nav>

        <div class="admin-sidebar-foot">
            <p class="admin-sidebar-tz">Ruang kerja analitik Gorontalo &middot; WITA</p>
            <form method="POST" action="{{ route('keluar') }}" class="admin-logout-form">
                @csrf
                <button type="submit" class="admin-logout-btn">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </aside>
@else
    <header>
        <strong>Ira Stationery</strong>
        <button type="button" class="topbar-hamburger" data-topbar-menu-open aria-expanded="false" aria-controls="topbar-nav" aria-label="Buka menu navigasi">☰</button>
        <nav id="topbar-nav" aria-label="Menu utama">
            @auth('web')
                <a href="{{ route('katalog') }}">Katalog</a>
                <a href="{{ route('keranjang.index') }}">Keranjang{{ ($cartItemCount ?? 0) > 0 ? " ($cartItemCount)" : '' }}</a>
                <a href="{{ route('pesanan.index') }}">Pesanan</a>
                <a href="{{ route('pembayaran.index') }}">Pembayaran</a>
                <a href="{{ route('profil.show') }}">Profil</a>
                <form method="POST" action="{{ route('keluar') }}" style="display:inline">
                    @csrf
                    <button type="submit">Keluar</button>
                </form>
            @endauth
            @auth('developer')
                <a href="{{ route('developer.dashboard') }}">Panel Developer</a>
                <a href="{{ route('developer.akun-simulasi.index') }}">Akun Simulasi</a>
                <form method="POST" action="{{ route('developer.keluar') }}" style="display:inline">
                    @csrf
                    <button type="submit">Keluar Developer</button>
                </form>
            @endauth
            @guest('web')
                @guest('developer')
                    <a href="{{ route('masuk') }}">Masuk</a>
                    <a href="{{ route('daftar') }}">Daftar Pembeli</a>
                @endguest
            @endguest
        </nav>
    </header>
    <div class="topbar-backdrop" data-topbar-menu-close hidden></div>
@endif
<main>
    @if (session('status'))
        <p class="alert-success">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul class="alert-error">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    @yield('content')
</main>
<footer>
    <small>Ira Stationery &bull; Gorontalo &bull; WITA (Asia/Makassar)</small>
</footer>
</body>
</html>
