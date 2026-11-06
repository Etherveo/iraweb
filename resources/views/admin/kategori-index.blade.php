@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4a (UI.md §6.6, §9.4 + Figma "Kategori produk").
         Semua route, form DELETE + konfirmasi, dan teks asersi tetap sama. --}}
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko &bull; Kategori produk</p>
                <h1 class="admin-title">Kategori Produk</h1>
                <p class="admin-subtitle">Rapikan pengelompokan produk toko Anda. Hanya kategori tanpa produk yang dapat dihapus.</p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-primary" href="{{ route('admin.kategori.create') }}">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    <span>Tambah kategori</span>
                </a>
            </div>
        </header>

        <div class="card admin-panel">
            @if ($categories->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada kategori.</p>
                    <p class="admin-empty-text">Tambahkan kategori pertama untuk mulai mengelompokkan produk toko.</p>
                    <p><a class="btn-primary" href="{{ route('admin.kategori.create') }}">Tambah kategori</a></p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Slug</th>
                                <th>Jumlah produk</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td data-label="Nama">
                                    <span class="admin-cell-primary">{{ $category->name }}</span>
                                </td>
                                <td data-label="Slug">
                                    <code class="admin-code">{{ $category->slug }}</code>
                                </td>
                                <td data-label="Jumlah produk">
                                    <span class="admin-count-badge">{{ $category->products_count }}</span>
                                </td>
                                <td data-label="Aksi">
                                    <div class="admin-row-actions">
                                        <a class="admin-action-link" href="{{ route('admin.kategori.edit', $category) }}">Ubah</a>
                                        <form method="POST" action="{{ route('admin.kategori.destroy', $category) }}" class="admin-inline-form" onsubmit="return confirm('Hapus kategori “{{ $category->name }}”? Kategori yang masih memiliki produk tidak dapat dihapus.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-danger-ghost" type="submit">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $categories->links() }}</div>
            @endif
        </div>
    </div>
@endsection
