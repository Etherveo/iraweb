@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 6 (UI.md §6, §9.2, §16, referensi Figma 3:103646).
         Semua form (PUT ubah jumlah, DELETE hapus item), batas 1–99, dan
         penanganan blokir checkout dipertahankan. --}}
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Keranjang</p>
                <h1 class="admin-title">Keranjang</h1>
                <p class="admin-subtitle">
                    @if ($items->isEmpty())
                        Belum ada barang yang kamu pilih.
                    @else
                        {{ $items->count() }} jenis produk &bull; {{ $items->sum('quantity') }} barang. Periksa jumlah sebelum melanjutkan ke pembayaran.
                    @endif
                </p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-secondary" href="{{ route('katalog') }}">Lanjut belanja</a>
            </div>
        </header>

        @if ($items->isEmpty())
            <div class="card admin-panel">
                <div class="admin-empty">
                    <p class="admin-empty-title">Keranjang masih kosong.</p>
                    <p><a class="admin-cell-link" href="{{ route('katalog') }}">Lihat katalog</a> untuk mulai berbelanja.</p>
                </div>
            </div>
        @else
            <div class="buyer-split-layout">
                <div class="card admin-panel">
                    <h2 class="admin-section-title">Barang pilihanmu</h2>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Harga satuan</th>
                                    <th>Jumlah</th>
                                    <th>Subtotal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td data-label="Produk">
                                        <div class="cart-item-info">
                                            <a class="admin-cell-link" href="{{ route('produk.show', $item->product) }}">{{ $item->product->name }}</a>
                                            <span class="admin-cell-sub">Stok tersedia: {{ $item->product->stock_available }}</span>
                                            @if ($item->isBlocked())
                                                <p class="cart-item-blocked"><strong>Perhatian:</strong> {{ $item->blockedReason() }}</p>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-label="Harga satuan" class="admin-cell-nowrap">{{ $item->product->formattedPrice() }}</td>
                                    <td data-label="Jumlah">
                                        <form method="POST" action="{{ route('keranjang.update', $item) }}" class="cart-qty-form">
                                            @csrf
                                            @method('PUT')
                                            <label class="sr-only" for="jumlah-{{ $item->id }}">Jumlah</label>
                                            <div class="stepper stepper-sm" data-stepper>
                                                <button type="button" class="stepper-btn" data-stepper-minus aria-label="Kurangi jumlah" hidden>&minus;</button>
                                                <input class="input stepper-input" id="jumlah-{{ $item->id }}" type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="99">
                                                <button type="button" class="stepper-btn" data-stepper-plus aria-label="Tambah jumlah" hidden>+</button>
                                            </div>
                                            <button class="btn-ghost-sm" type="submit">Ubah</button>
                                        </form>
                                    </td>
                                    <td data-label="Subtotal" class="admin-cell-price">{{ 'Rp '.number_format($item->subtotalIdr(), 0, ',', '.') }}</td>
                                    <td data-label="Aksi">
                                        <form method="POST" action="{{ route('keranjang.destroy', $item) }}" class="admin-inline-form">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-danger-ghost" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="card admin-panel buyer-summary-card" aria-label="Ringkasan belanja">
                    <h2 class="admin-section-title">Ringkasan belanja</h2>
                    <ul class="buyer-summary-lines">
                        @foreach ($items as $item)
                            <li>
                                <span>{{ $item->product->name }} &times; {{ $item->quantity }}</span>
                                <strong>{{ 'Rp '.number_format($item->subtotalIdr(), 0, ',', '.') }}</strong>
                            </li>
                        @endforeach
                    </ul>
                    <p class="buyer-total-line">
                        <span>Total:</span>
                        <strong>{{ 'Rp '.number_format($total_idr, 0, ',', '.') }}</strong>
                    </p>

                    @if ($blocked->isNotEmpty())
                        <p class="buyer-blocked-banner">Checkout diblokir: perbaiki jumlah atau hapus item yang bermasalah di atas.</p>
                    @else
                        <a class="btn-primary buyer-cta-block" href="{{ route('checkout.show') }}">Lanjut ke Checkout</a>
                    @endif

                    <p class="buyer-summary-note">Total dihitung dari harga satuan &times; jumlah barang di server.</p>
                </aside>
            </div>
        @endif
    </div>
@endsection
