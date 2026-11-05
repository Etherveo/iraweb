@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Kategori Produk</h1>
        <p><a href="{{ route('admin.kategori.create') }}">Tambah kategori</a></p>
        @if ($categories->isEmpty())
            <p>Belum ada kategori.</p>
            <p><a href="{{ route('admin.kategori.create') }}">Tambah kategori</a></p>
        @else
            <table>
                <thead><tr><th>Nama</th><th>Slug</th><th>Jumlah produk</th><th>Aksi</th></tr></thead>
                <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->slug }}</td>
                        <td>{{ $category->products_count }}</td>
                        <td>
                            <a href="{{ route('admin.kategori.edit', $category) }}">Ubah</a>
                            <form method="POST" action="{{ route('admin.kategori.destroy', $category) }}" style="display:inline" onsubmit="return confirm('Hapus kategori “{{ $category->name }}”? Kategori yang masih memiliki produk tidak dapat dihapus.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $categories->links() }}
        @endif
    </div>
@endsection
