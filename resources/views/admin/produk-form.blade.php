@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>{{ $product ? 'Ubah Produk' : 'Tambah Produk' }}</h1>
        <form method="POST" action="{{ $product ? route('admin.produk.update', $product) : route('admin.produk.store') }}">
            @csrf
            @if ($product)
                @method('PUT')
            @endif
            <div>
                <label for="name">Nama produk (2–150 karakter)</label>
                <input class="input" id="name" name="name" value="{{ old('name', $product?->name) }}" required>
                @error('name')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="category_id">Kategori</label>
                <select class="input" id="category_id" name="category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description">Deskripsi (opsional, maksimal 2000 karakter)</label>
                <textarea class="input" id="description" name="description">{{ old('description', $product?->description) }}</textarea>
                @error('description')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="price_idr">Harga (rupiah, bilangan bulat 1–100.000.000)</label>
                <input class="input" id="price_idr" name="price_idr" type="number" step="1" min="1" max="100000000" value="{{ old('price_idr', $product?->price_idr) }}" required>
                @error('price_idr')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            @if (! $product)
                <div>
                    <label for="stock_on_hand">Stok awal (0–1.000.000)</label>
                    <input class="input" id="stock_on_hand" name="stock_on_hand" type="number" step="1" min="0" max="1000000" value="{{ old('stock_on_hand', 0) }}" required>
                    @error('stock_on_hand')<p class="alert-error">{{ $message }}</p>@enderror
                    <p>Stok awal di atas 0 akan dicatat sebagai satu entri riwayat penyesuaian. Perubahan stok berikutnya lewat halaman Atur stok, bukan di sini.</p>
                </div>
            @endif
            <div>
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true))> Aktif (produk nonaktif tidak tampil di katalog pembeli)</label>
            </div>
            <button class="btn-primary" type="submit">{{ $product ? 'Simpan perubahan' : 'Tambah produk' }}</button>
            <a href="{{ route('admin.produk.index') }}">Batal</a>
        </form>
    </div>
@endsection
