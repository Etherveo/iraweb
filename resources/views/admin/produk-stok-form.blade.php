@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4a (UI.md §6.1, §6.3, §9.4 + Figma "Atur stok").
         Ringkasan tiga angka stok, form POST stock_on_hand + note, dan
         seluruh teks tetap sama. --}}
    @php($habis = $product->stock_available <= 0)
    <div class="admin-page admin-page-narrow">
        <nav class="admin-back-nav" aria-label="Navigasi stok">
            <a class="admin-back-link" href="{{ route('admin.produk.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke produk</span>
            </a>
            <a class="admin-back-link" href="{{ route('admin.produk.riwayat', $product) }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                <span>Riwayat stok</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko &bull; Produk</p>
                <h1 class="admin-title">Atur Stok: {{ $product->name }}</h1>
                <p class="admin-subtitle">Tetapkan jumlah fisik hasil pemeriksaan persediaan.</p>
            </div>
        </header>

        <div class="card admin-panel">
            <div class="admin-stat-grid">
                <div class="admin-stat-box">
                    <p class="admin-stat-label">Stok fisik saat ini</p>
                    <p class="admin-stat-value">{{ $product->stock_on_hand }}</p>
                </div>
                <div class="admin-stat-box">
                    <p class="admin-stat-label">Stok dicadangkan</p>
                    <p class="admin-stat-value">{{ $product->stock_reserved }}</p>
                </div>
                <div class="admin-stat-box">
                    <p class="admin-stat-label">Stok tersedia</p>
                    <p @class(['admin-stat-value', 'is-out' => $habis])>{{ $habis ? 'Habis' : $product->stock_available }}</p>
                </div>
            </div>
            <p class="admin-info-box">Isi stok fisik BARU (bukan selisih). Nilai baru tidak boleh lebih kecil dari stok dicadangkan.</p>
        </div>

        <div class="card admin-panel">
            <h2 class="admin-section-title">Penyesuaian stok fisik</h2>
            <form method="POST" action="{{ route('admin.produk.stok.update', $product) }}" class="admin-form">
                @csrf
                <div class="admin-field">
                    <label for="stock_on_hand" class="admin-label">Stok fisik baru (0–1.000.000)</label>
                    <input class="input" id="stock_on_hand" name="stock_on_hand" type="number" step="1" min="0" max="1000000" value="{{ old('stock_on_hand', $product->stock_on_hand) }}" required
                           @error('stock_on_hand') aria-invalid="true" aria-describedby="stock-error" @enderror>
                    @error('stock_on_hand')<p class="alert-error field-error" id="stock-error">{{ $message }}</p>@enderror
                </div>

                <div class="admin-field">
                    <label for="note" class="admin-label">Alasan (opsional)</label>
                    <input class="input" id="note" name="note" value="{{ old('note') }}" placeholder="Contoh: Penerimaan stok dari pemasok"
                           @error('note') aria-invalid="true" aria-describedby="note-error" @enderror>
                    @error('note')<p class="alert-error field-error" id="note-error">{{ $message }}</p>@enderror
                </div>

                <div class="admin-form-actions">
                    <button class="btn-primary" type="submit">Simpan stok</button>
                    <a class="btn-secondary" href="{{ route('admin.produk.index') }}">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
