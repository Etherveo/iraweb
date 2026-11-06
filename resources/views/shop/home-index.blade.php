@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 3: halaman home publik di `/`. Tiga section produk
         aktif: Terlaris (disembunyikan bila belum ada pesanan berbayar),
         Terbaru, dan Teratas (dengan tombol "Lihat selengkapnya" ke /katalog).
         Tidak ada form beli dan tidak mencatat event view. --}}
    @php($gradients = ['lavender', 'blue', 'purple', 'cyan'])
    <div class="shop-page">
        <header class="shop-hero home-hero">
            <div>
                <p class="shop-eyebrow">Ira Stationery &bull; Gorontalo</p>
                <h1 class="shop-title">Keperluan kecil untuk ide besar.</h1>
                <p class="shop-lead">Pilihan buku, alat tulis, dan perlengkapan sekolah untuk menemani aktivitasmu setiap hari.</p>
            </div>
            <div class="home-hero-actions">
                <a class="btn-primary" href="{{ route('katalog') }}">Jelajahi Katalog</a>
            </div>
        </header>

        @if ($bestSellers->isNotEmpty())
            <section class="home-section" aria-labelledby="section-terlaris">
                <div class="home-section-head">
                    <div>
                        <p class="shop-eyebrow">Pilihan populer</p>
                        <h2 id="section-terlaris" class="home-section-title">Terlaris</h2>
                    </div>
                </div>
                <ul class="product-grid">
                    @foreach ($bestSellers as $product)
                        @php($habis = $product->stock_available <= 0)
                        <li class="product-card">
                            <a class="product-card-link" href="{{ route('produk.show', $product) }}">
                                <div class="product-art product-art-{{ $gradients[$product->category_id % 4] ?? 'lavender' }}" aria-hidden="true">
                                    <span class="product-art-tag">{{ $product->category?->name }}</span>
                                    <span class="product-art-badge">{{ (int) $product->sold_quantity }} terjual</span>
                                    <span class="product-art-tile">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/></svg>
                                    </span>
                                </div>
                                <h3 class="product-name">{{ $product->name }}</h3>
                                <p class="product-category">{{ $product->category?->name }}</p>
                                <p class="product-price">{{ $product->formattedPrice() }}</p>
                                <p @class(['product-stock', 'is-out' => $habis])>
                                    @if ($habis)
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                                        Habis
                                    @else
                                        Tersedia: {{ $product->stock_available }}
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="home-section" aria-labelledby="section-terbaru">
            <div class="home-section-head">
                <div>
                    <p class="shop-eyebrow">Baru ditambahkan</p>
                    <h2 id="section-terbaru" class="home-section-title">Terbaru</h2>
                </div>
            </div>
            @if ($newestProducts->isEmpty())
                <div class="shop-empty">
                    <p class="shop-empty-title">Belum ada produk aktif.</p>
                </div>
            @else
                <ul class="product-grid">
                    @foreach ($newestProducts as $product)
                        @php($habis = $product->stock_available <= 0)
                        <li class="product-card">
                            <a class="product-card-link" href="{{ route('produk.show', $product) }}">
                                <div class="product-art product-art-{{ $gradients[$product->category_id % 4] ?? 'lavender' }}" aria-hidden="true">
                                    <span class="product-art-tag">{{ $product->category?->name }}</span>
                                    <span class="product-art-tile">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/></svg>
                                    </span>
                                </div>
                                <h3 class="product-name">{{ $product->name }}</h3>
                                <p class="product-category">{{ $product->category?->name }}</p>
                                <p class="product-price">{{ $product->formattedPrice() }}</p>
                                <p @class(['product-stock', 'is-out' => $habis])>
                                    @if ($habis)
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                                        Habis
                                    @else
                                        Tersedia: {{ $product->stock_available }}
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="home-section" aria-labelledby="section-teratas">
            <div class="home-section-head">
                <div>
                    <p class="shop-eyebrow">Katalog utama</p>
                    <h2 id="section-teratas" class="home-section-title">Teratas</h2>
                </div>
                <a class="btn-secondary home-more-btn" href="{{ route('katalog') }}">
                    <span>Lihat selengkapnya</span>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
            @if ($topProducts->isEmpty())
                <div class="shop-empty">
                    <p class="shop-empty-title">Belum ada produk aktif.</p>
                </div>
            @else
                <ul class="product-grid">
                    @foreach ($topProducts as $product)
                        @php($habis = $product->stock_available <= 0)
                        <li class="product-card">
                            <a class="product-card-link" href="{{ route('produk.show', $product) }}">
                                <div class="product-art product-art-{{ $gradients[$product->category_id % 4] ?? 'lavender' }}" aria-hidden="true">
                                    <span class="product-art-tag">{{ $product->category?->name }}</span>
                                    <span class="product-art-tile">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/></svg>
                                    </span>
                                </div>
                                <h3 class="product-name">{{ $product->name }}</h3>
                                <p class="product-category">{{ $product->category?->name }}</p>
                                <p class="product-price">{{ $product->formattedPrice() }}</p>
                                <p @class(['product-stock', 'is-out' => $habis])>
                                    @if ($habis)
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                                        Habis
                                    @else
                                        Tersedia: {{ $product->stock_available }}
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection

