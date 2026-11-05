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
        @if ($product->stock_available > 0)
            <form method="POST" action="{{ route('keranjang.tambah') }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <div>
                    <label for="jumlah">Jumlah</label>
                    <input class="input" id="jumlah" type="number" name="quantity" value="1" min="1" max="99">
                </div>
                <button class="btn-primary" type="submit">Tambah ke Keranjang</button>
            </form>
        @else
            <button class="btn-primary" type="button" disabled>Tambah ke Keranjang</button>
            <p>Stok produk ini sedang habis.</p>
        @endif
        <p><a href="{{ route('katalog') }}">Kembali ke katalog</a></p>
    </div>
@endsection
