@extends('layouts.app')

@section('content')
    {{-- CRUD katalog di Panel Developer (keputusan Ren 2026-11-10).
         Daftar yang sama dengan admin, ditambah kolom Rilis. Atur stok
         + riwayat stok tetap admin-only, jadi tidak ada di sini. --}}
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Panel Developer &bull; Produk</p>
                <h1 class="admin-title">Produk</h1>
                <p class="admin-subtitle">Kelola informasi, status, dan persediaan produk. Tersedia = stok fisik dikurangi dicadangkan. Kolom Rilis menampilkan tanggal rilis (awal deret tren).</p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-primary" href="{{ route('developer.produk.create') }}">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    <span>Tambah produk</span>
                </a>
            </div>
        </header>

        <div class="card admin-panel">
            <form method="GET" action="{{ route('developer.produk.index') }}" class="admin-filters">
                <div class="admin-filter-group admin-filter-grow">
                    <label for="filter-cari" class="admin-label">Cari produk</label>
                    <input class="input" id="filter-cari" name="cari" value="{{ request('cari') }}" placeholder="Cari nama produk">
                </div>
                <div class="admin-filter-group">
                    <label for="filter-kategori" class="admin-label">Kategori</label>
                    <select class="input" id="filter-kategori" name="kategori">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('kategori') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-group">
                    <label for="filter-status" class="admin-label">Status</label>
                    <select class="input" id="filter-status" name="status">
                        <option value="">Semua status</option>
                        <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button class="btn-primary" type="submit">Cari</button>
                    @if (request()->filled('cari') || request()->filled('kategori') || request()->filled('status'))
                        <a class="btn-secondary" href="{{ route('developer.produk.index') }}">Reset</a>
                    @endif
                </div>
            </form>

            @if ($products->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada produk.</p>
                    <p class="admin-empty-text">Tambahkan produk baru atau ubah kata kunci filter di atas.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Rilis</th>
                                <th>Stok fisik</th>
                                <th>Dicadangkan</th>
                                <th>Tersedia</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($products as $product)
                            @php($habis = $product->stock_available <= 0)
                            <tr>
                                <td data-label="Nama">
                                    <span class="admin-cell-primary">{{ $product->name }}</span>
                                </td>
                                <td data-label="Kategori">{{ $product->category?->name }}</td>
                                <td data-label="Harga">
                                    <span class="admin-cell-price">{{ $product->formattedPrice() }}</span>
                                </td>
                                <td data-label="Rilis">{{ $product->release_date ? $product->release_date->format('d M Y') : '—' }}</td>
                                <td data-label="Stok fisik">{{ $product->stock_on_hand }}</td>
                                <td data-label="Dicadangkan">{{ $product->stock_reserved }}</td>
                                <td data-label="Tersedia">
                                    @if ($habis)
                                        <span class="status-chip status-chip-warning">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                            <span>Habis</span>
                                        </span>
                                    @else
                                        <strong class="admin-cell-strong">{{ $product->stock_available }}</strong>
                                    @endif
                                </td>
                                <td data-label="Status">
                                    @if ($product->is_active)
                                        <span class="status-chip status-chip-success">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                                            <span>Aktif</span>
                                        </span>
                                    @else
                                        <span class="status-chip status-chip-muted">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                                            <span>Nonaktif</span>
                                        </span>
                                    @endif
                                </td>
                                <td data-label="Aksi">
                                    <div class="admin-row-actions">
                                        <a class="admin-action-link" href="{{ route('developer.produk.edit', $product) }}">Ubah</a>
                                        <form method="POST" action="{{ route('developer.produk.toggle', $product) }}" class="admin-inline-form">
                                            @csrf
                                            <button class="btn-ghost-sm" type="submit">{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
@endsection
