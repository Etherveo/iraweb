@extends('layouts.app')

@section('content')
    {{-- CRUD katalog di Panel Developer (keputusan Ren 2026-11-10).
         Form yang sama dengan admin, ditambah kolom release_date. --}}
    <div class="admin-page admin-page-narrow">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('developer.produk.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke produk</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Panel Developer &bull; Produk</p>
                <h1 class="admin-title">{{ $product ? 'Ubah Produk' : 'Tambah Produk' }}</h1>
                <p class="admin-subtitle">Lengkapi informasi produk, harga, dan status ketersediaan di katalog.</p>
            </div>
        </header>

        <div class="card admin-panel">
            <form method="POST" action="{{ $product ? route('developer.produk.update', $product) : route('developer.produk.store') }}" class="admin-form">
                @csrf
                @if ($product)
                    @method('PUT')
                @endif

                <div class="admin-field">
                    <label for="name" class="admin-label">Nama produk (2–150 karakter)</label>
                    <input class="input" id="name" name="name" value="{{ old('name', $product?->name) }}" required
                           @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    @error('name')<p class="alert-error field-error" id="name-error">{{ $message }}</p>@enderror
                </div>

                <div class="admin-field">
                    <label for="category_id" class="admin-label">Kategori</label>
                    <select class="input" id="category_id" name="category_id" required
                            @error('category_id') aria-invalid="true" aria-describedby="category-error" @enderror>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<p class="alert-error field-error" id="category-error">{{ $message }}</p>@enderror
                </div>

                <div class="admin-field">
                    <label for="description" class="admin-label">Deskripsi (opsional, maksimal 2000 karakter)</label>
                    <textarea class="input admin-textarea" id="description" name="description" rows="4"
                              @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $product?->description) }}</textarea>
                    @error('description')<p class="alert-error field-error" id="description-error">{{ $message }}</p>@enderror
                </div>

                <div class="admin-field-grid">
                    <div class="admin-field">
                        <label for="price_idr" class="admin-label">Harga (rupiah, bilangan bulat 1–100.000.000)</label>
                        <input class="input" id="price_idr" name="price_idr" type="number" step="1" min="1" max="100000000" value="{{ old('price_idr', $product?->price_idr) }}" required
                               @error('price_idr') aria-invalid="true" aria-describedby="price-error" @enderror>
                        @error('price_idr')<p class="alert-error field-error" id="price-error">{{ $message }}</p>@enderror
                    </div>

                    @if (! $product)
                        <div class="admin-field">
                            <label for="stock_on_hand" class="admin-label">Stok awal (0–1.000.000)</label>
                            <input class="input" id="stock_on_hand" name="stock_on_hand" type="number" step="1" min="0" max="1000000" value="{{ old('stock_on_hand', 0) }}" required
                                   @error('stock_on_hand') aria-invalid="true" aria-describedby="stock-error" @enderror>
                            @error('stock_on_hand')<p class="alert-error field-error" id="stock-error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </div>

                @if (! $product)
                    <p class="admin-info-box">Stok awal di atas 0 akan dicatat sebagai satu entri riwayat penyesuaian. Perubahan stok berikutnya lewat halaman Atur stok, bukan di sini.</p>
                @endif

                <div class="admin-field">
                    <label for="release_date" class="admin-label">Tanggal rilis (opsional)</label>
                    <input class="input" id="release_date" name="release_date" type="date" value="{{ old('release_date', $product?->release_date?->format('Y-m-d')) }}"
                           @error('release_date') aria-invalid="true" aria-describedby="release_date-error" @enderror>
                    @error('release_date')<p class="alert-error field-error" id="release_date-error">{{ $message }}</p>@enderror
                    <p class="admin-hint">Tanggal produk mulai dijual — dipakai sebagai awal deret tren (FR-02). Boleh tanggal lampau (mis. Sep 2026) supaya riwayat simulasi terhitung. Kosong: awal deret = minggu pertama produk terjual.</p>
                </div>

                <div class="admin-field">
                    <label class="admin-check-label">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true))>
                        <span>Aktif (produk nonaktif tidak tampil di katalog pembeli)</span>
                    </label>
                </div>

                <div class="admin-form-actions">
                    <button class="btn-primary" type="submit">{{ $product ? 'Simpan perubahan' : 'Tambah produk' }}</button>
                    <a class="btn-secondary" href="{{ route('developer.produk.index') }}">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
