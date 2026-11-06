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
<body>
<header>
    <strong>Ira Stationery</strong>
    <nav>
        @auth('web')
            @if (auth('web')->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.dashboard')]) @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard Admin</a>
                <a href="{{ route('admin.kategori.index') }}" @class(['is-active' => request()->routeIs('admin.kategori.*')]) @if (request()->routeIs('admin.kategori.*')) aria-current="page" @endif>Kategori</a>
                <a href="{{ route('admin.produk.index') }}" @class(['is-active' => request()->routeIs('admin.produk.*')]) @if (request()->routeIs('admin.produk.*')) aria-current="page" @endif>Produk</a>
                <a href="{{ route('admin.pesanan.index') }}" @class(['is-active' => request()->routeIs('admin.pesanan.*')]) @if (request()->routeIs('admin.pesanan.*')) aria-current="page" @endif>Pesanan</a>
                <a href="{{ route('admin.pembayaran.index') }}" @class(['is-active' => request()->routeIs('admin.pembayaran.*')]) @if (request()->routeIs('admin.pembayaran.*')) aria-current="page" @endif>Pembayaran</a>
                <a href="{{ route('admin.akun-pembeli.index') }}" @class(['is-active' => request()->routeIs('admin.akun-pembeli.*')]) @if (request()->routeIs('admin.akun-pembeli.*')) aria-current="page" @endif>Akun Pembeli</a>
            @else
                <a href="{{ route('katalog') }}">Katalog</a>
                <a href="{{ route('keranjang.index') }}">Keranjang{{ ($cartItemCount ?? 0) > 0 ? " ($cartItemCount)" : '' }}</a>
                <a href="{{ route('pesanan.index') }}">Pesanan</a>
                <a href="{{ route('pembayaran.index') }}">Pembayaran</a>
            @endif
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
