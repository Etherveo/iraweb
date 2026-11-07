@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4a (UI.md §6.6, §9.4 + Figma "Riwayat stok").
         Kolom, format waktu WITA, nilai $movement->type, pagination, dan
         tautan kembali tetap sama. --}}
    <div class="admin-page">
        <nav class="admin-back-nav" aria-label="Navigasi riwayat stok">
            <a class="admin-back-link" href="{{ route('admin.produk.stok', $product) }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke atur stok</span>
            </a>
            <a class="admin-back-link" href="{{ route('admin.produk.index') }}">
                <span>Kembali ke daftar produk</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko &bull; Produk</p>
                <h1 class="admin-title">Riwayat Stok: {{ $product->name }}</h1>
                <p class="admin-subtitle">Riwayat ini baca-saja dan tidak dapat diubah — setiap perubahan stok menambah satu baris baru.</p>
            </div>
        </header>

        <div class="card admin-panel">
            <div class="admin-product-strip">
                <div>
                    <p class="admin-cell-primary">{{ $product->name }}</p>
                    <p class="admin-hint">Fisik {{ $product->stock_on_hand }} &bull; Dicadangkan {{ $product->stock_reserved }} &bull; Tersedia {{ $product->stock_available > 0 ? $product->stock_available : 'Habis' }}</p>
                </div>
            </div>

            @if ($movements->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada riwayat stok untuk produk ini.</p>
                    <p class="admin-empty-text">Perubahan stok fisik maupun cadangan pesanan akan tercatat secara berurutan di sini.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Waktu (WITA)</th>
                                <th>Jenis</th>
                                <th>Perubahan fisik</th>
                                <th>Perubahan dicadangkan</th>
                                <th>Referensi pesanan</th>
                                <th>Pelaku</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($movements as $movement)
                            <tr>
                                <td data-label="Waktu (WITA)">
                                    <span class="admin-cell-nowrap">{{ $movement->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</span>
                                </td>
                                <td data-label="Jenis">
                                    <span class="status-chip status-chip-info">{{ $movement->type }}</span>
                                </td>
                                <td data-label="Perubahan fisik">
                                    <code class="admin-code">{{ $movement->delta_on_hand > 0 ? '+'.$movement->delta_on_hand : $movement->delta_on_hand }}</code>
                                </td>
                                <td data-label="Perubahan dicadangkan">
                                    <code class="admin-code">{{ $movement->delta_reserved > 0 ? '+'.$movement->delta_reserved : $movement->delta_reserved }}</code>
                                </td>
                                <td data-label="Referensi pesanan">{{ $movement->order_id ?? '-' }}</td>
                                <td data-label="Pelaku">{{ $movement->actor_type }}{{ $movement->actor_id ? ' #'.$movement->actor_id : '' }}</td>
                                <td data-label="Catatan">{{ $movement->note ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $movements->links() }}</div>
            @endif
        </div>
    </div>
@endsection
