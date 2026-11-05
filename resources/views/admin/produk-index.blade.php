@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Produk</h1>
        <p><a href="{{ route('admin.produk.create') }}">Tambah produk</a></p>
        <form method="GET" action="{{ route('admin.produk.index') }}">
            <input class="input" name="cari" value="{{ request('cari') }}" placeholder="Cari nama produk">
            <select name="kategori">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('kategori') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">Semua status</option>
                <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
            </select>
            <button class="btn-primary" type="submit">Cari</button>
        </form>
        @if ($products->isEmpty())
            <p>Belum ada produk.</p>
        @else
            <table>
                <thead><tr><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok fisik</th><th>Dicadangkan</th><th>Tersedia</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @foreach ($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category?->name }}</td>
                        <td>{{ $product->formattedPrice() }}</td>
                        <td>{{ $product->stock_on_hand }}</td>
                        <td>{{ $product->stock_reserved }}</td>
                        <td>{{ $product->stock_available > 0 ? $product->stock_available : 'Habis' }}</td>
                        <td>{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td>
                            <a href="{{ route('admin.produk.edit', $product) }}">Ubah</a>
                            <a href="{{ route('admin.produk.stok', $product) }}">Atur stok</a>
                            <a href="{{ route('admin.produk.riwayat', $product) }}">Riwayat stok</a>
                            <form method="POST" action="{{ route('admin.produk.toggle', $product) }}" style="display:inline">
                                @csrf
                                <button type="submit">{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $products->links() }}
        @endif
    </div>
@endsection
