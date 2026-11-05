@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Katalog</h1>
        <form method="GET" action="{{ route('katalog') }}">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Cari produk (minimal 2 karakter)">
            <select name="kategori">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('kategori') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button class="btn-primary" type="submit">Cari</button>
            @if (request()->filled('q') || request()->filled('kategori'))
                <a href="{{ route('katalog') }}">Bersihkan pencarian</a>
            @endif
        </form>
        @if (request()->filled('q'))
            <p>Hasil pencarian untuk: {{ request('q') }}</p>
        @endif
        @if ($products->isEmpty())
            <p>Tidak ada produk yang ditemukan.</p>
            @if (request()->filled('q') || request()->filled('kategori'))
                <p><a href="{{ route('katalog') }}">Bersihkan pencarian</a> untuk melihat semua produk.</p>
            @endif
        @else
            <ul>
                @foreach ($products as $product)
                    <li>
                        <a href="{{ route('produk.show', $product) }}">{{ $product->name }}</a>
                        <span>{{ $product->category?->name }}</span>
                        <span>{{ $product->formattedPrice() }}</span>
                        <span>{{ $product->stock_available > 0 ? 'Tersedia: '.$product->stock_available : 'Habis' }}</span>
                    </li>
                @endforeach
            </ul>
            {{ $products->links() }}
        @endif
    </div>
@endsection
