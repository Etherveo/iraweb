@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 7 (UI.md §9.3, §10, §16, referensi Figma 3:106093).
         Label STRUK SIMULASI — bukan bukti pembayaran sah tetap tercetak
         bersama rincian barang; tombol Cetak Struk (window.print) dipertahankan. --}}
    <div class="admin-page pay-receipt-wrap">
        <header class="admin-head no-print">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Pembayaran</p>
                <h1 class="admin-title">Struk Pembayaran</h1>
                <p class="admin-subtitle">Pratinjau dokumen cetak struk pembayaran pesanan.</p>
            </div>
            <div class="admin-head-actions">
                <button type="button" class="btn-primary" onclick="window.print()">Cetak Struk</button>
                <a class="btn-secondary" href="{{ route('pembayaran.hasil', $payment) }}">Kembali ke halaman hasil</a>
            </div>
        </header>

        <article class="card receipt-sheet" aria-label="Struk pembayaran">
            <p class="receipt-store"><strong>Ira Stationery — Gorontalo</strong></p>
            <p class="receipt-caption">Bukti pembayaran pesanan toko.</p>

            <dl class="admin-kv-list receipt-meta">
                <div class="admin-kv-row">
                    <dt class="admin-kv-row-label">Nomor pesanan</dt>
                    <dd class="admin-kv-row-val"><strong>{{ $order->order_number }}</strong></dd>
                </div>
                <div class="admin-kv-row">
                    <dt class="admin-kv-row-label">Kode referensi</dt>
                    <dd class="admin-kv-row-val"><code class="admin-code">{{ $payment->reference_code }}</code></dd>
                </div>
                <div class="admin-kv-row">
                    <dt class="admin-kv-row-label">Metode</dt>
                    <dd class="admin-kv-row-val"><strong>{{ $method['label'] ?? $payment->payment_method }}</strong></dd>
                </div>
                <div class="admin-kv-row">
                    <dt class="admin-kv-row-label">Dibayar</dt>
                    <dd class="admin-kv-row-val">{{ $payment->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                </div>
            </dl>

            @include('payments._pickup-instruction', ['order' => $order])

            <h2 class="admin-section-title receipt-items-title">Item</h2>
            <ul class="buyer-summary-lines receipt-items">
                @foreach ($order->items as $item)
                    <li>
                        <span>{{ $item->product_name_snapshot }} &times; {{ $item->quantity }}</span>
                        <strong>{{ $item->formattedSubtotal() }}</strong>
                    </li>
                @endforeach
            </ul>

            <p class="buyer-total-line receipt-total">
                <span>Total:</span>
                <strong>{{ $order->formattedTotal() }}</strong>
            </p>

            <div class="receipt-foot">
                <span class="status-chip status-chip-success"><span>Berhasil</span></span>
            </div>
        </article>
    </div>
@endsection
