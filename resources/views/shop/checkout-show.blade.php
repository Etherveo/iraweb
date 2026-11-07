@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 6 (UI.md §6, §9.2, §9.3, §16, referensi Figma 3:103851).
         Form POST checkout.store dengan checkout_key tersembunyi, pelindung
         kirim-ganda, dan keenam metode pembayaran simulasi dipertahankan. --}}
    <div class="admin-page">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('keranjang.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke keranjang</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Checkout</p>
                <h1 class="admin-title">Checkout</h1>
                <p class="admin-subtitle">Periksa barang pesananmu, lalu pilih satu metode pembayaran.</p>
            </div>
        </header>

        <ol class="buyer-step-bar" aria-label="Tahapan pembelian">
            <li class="buyer-step-item is-done">1 Keranjang</li>
            <li class="buyer-step-item is-current" aria-current="step">2 Pembayaran</li>
            <li class="buyer-step-item">3 Hasil</li>
        </ol>

        <form method="POST" action="{{ route('checkout.store') }}" class="buyer-checkout-form" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf
            <input type="hidden" name="checkout_key" value="{{ $checkout_key }}">

            <div class="buyer-split-layout">
                <div class="buyer-main-stack">
                    <div class="card admin-panel">
                        <h2 class="admin-section-title">Barang pesanan</h2>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Harga satuan</th>
                                        <th>Jumlah</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td data-label="Produk" class="admin-cell-primary">{{ $item->product->name }}</td>
                                        <td data-label="Harga satuan" class="admin-cell-nowrap">{{ $item->product->formattedPrice() }}</td>
                                        <td data-label="Jumlah">{{ $item->quantity }}</td>
                                        <td data-label="Subtotal" class="admin-cell-price">{{ 'Rp '.number_format($item->subtotalIdr(), 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card admin-panel">
                        <fieldset class="buyer-method-fieldset">
                            <legend class="admin-section-title">Pilih metode pembayaran</legend>
                            <p class="admin-hint">Batas waktu bayar setelah pesanan dibuat: {{ $expiry_minutes }} menit.</p>
                            <div class="buyer-method-grid">
                                @foreach ($methods as $method)
                                    <label class="buyer-method-card" for="metode-{{ $method['code'] }}">
                                        <input type="radio" id="metode-{{ $method['code'] }}" name="payment_method" value="{{ $method['code'] }}" @checked($loop->first)>
                                        <span class="buyer-method-body">
                                            <span class="buyer-method-title">{{ $method['label'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
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
                    <button class="btn-primary buyer-cta-block" type="submit">Buat Pesanan</button>
                    <a class="btn-secondary buyer-cta-block" href="{{ route('keranjang.index') }}">Kembali ke keranjang</a>
                </aside>
            </div>
        </form>
    </div>
@endsection
