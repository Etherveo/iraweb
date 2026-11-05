@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Atur Stok: {{ $product->name }}</h1>
        <p>Stok fisik saat ini: {{ $product->stock_on_hand }}</p>
        <p>Stok dicadangkan: {{ $product->stock_reserved }}</p>
        <p>Stok tersedia: {{ $product->stock_available > 0 ? $product->stock_available : 'Habis' }}</p>
        <p>Isi stok fisik BARU (bukan selisih). Nilai baru tidak boleh lebih kecil dari stok dicadangkan.</p>
        <form method="POST" action="{{ route('admin.produk.stok.update', $product) }}">
            @csrf
            <div>
                <label for="stock_on_hand">Stok fisik baru (0–1.000.000)</label>
                <input class="input" id="stock_on_hand" name="stock_on_hand" type="number" step="1" min="0" max="1000000" value="{{ old('stock_on_hand', $product->stock_on_hand) }}" required>
                @error('stock_on_hand')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="note">Alasan (opsional)</label>
                <input class="input" id="note" name="note" value="{{ old('note') }}">
                @error('note')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary" type="submit">Simpan stok</button>
            <a href="{{ route('admin.produk.index') }}">Batal</a>
        </form>
    </div>
@endsection
