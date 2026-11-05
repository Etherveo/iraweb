@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>{{ $product->name }}</h1>
        <p>Kategori: {{ $product->category?->name }}</p>
        <p>Harga: {{ $product->formattedPrice() }}</p>
        @if ($product->description)
            <p>{{ $product->description }}</p>
        @endif
        <p>Stok tersedia: {{ $product->stock_available > 0 ? $product->stock_available : 'Habis' }}</p>
        <div>
            <label for="jumlah">Jumlah</label>
            <input class="input" id="jumlah" type="number" name="jumlah" value="1" min="1" max="99" @disabled($product->stock_available <= 0)>
        </div>
        {{-- M3 placeholder: there is no cart yet, so this button does
             nothing on purpose and says so. No POST cart route exists
             in M2; the real add-to-cart arrives with CRT-01 in M3a. --}}
        <button class="btn-primary" type="button" disabled>Tambah ke Keranjang — M3</button>
        <p>Keranjang dibangun di M3; tombol ini belum berfungsi.</p>
        <p><a href="{{ route('katalog') }}">Kembali ke katalog</a></p>
    </div>
@endsection
