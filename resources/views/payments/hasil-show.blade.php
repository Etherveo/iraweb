@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 7 (UI.md §9.3, §10, §16, referensi Figma 3:105680,
         3:105817, 3:105960). Tiga varian hasil (paid, failed, expired),
         form bayar-ulang, form batal, dan semua tautan dipertahankan. --}}
    <div class="admin-page pay-result-wrap">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Pembayaran</p>
                <p class="admin-subtitle">Status hasil transaksi pembayaran pesananmu.</p>
            </div>
        </header>

        <div class="card admin-panel pay-result-card">
            @if ($payment->status === 'paid')
                <div class="pay-result-icon is-success" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                </div>

                <h1 class="pay-result-title">Pembayaran Berhasil</h1>
                <p class="pay-result-desc">Pesanan <strong>{{ $order->order_number }}</strong> sudah dibayar.</p>

                <dl class="admin-kv-list">
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Nomor pesanan</dt>
                        <dd class="admin-kv-row-val"><strong>{{ $order->order_number }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Total</dt>
                        <dd class="admin-kv-row-val"><strong class="admin-cell-price">{{ $order->formattedTotal() }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Metode</dt>
                        <dd class="admin-kv-row-val"><strong>{{ $method['label'] ?? $payment->payment_method }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Kode referensi</dt>
                        <dd class="admin-kv-row-val"><code class="admin-code">{{ $payment->reference_code }}</code></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Dibayar</dt>
                        <dd class="admin-kv-row-val">{{ $payment->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Status pembayaran</dt>
                        <dd class="admin-kv-row-val">
                            <span class="status-chip status-chip-success"><span>{{ $payment->statusLabel() }}</span></span>
                        </dd>
                    </div>
                </dl>

                <div class="pay-cta-stack">
                    <a class="btn-primary buyer-cta-block" href="{{ route('pesanan.show', $order) }}">Lihat detail pesanan</a>
                    <a class="btn-secondary buyer-cta-block" href="{{ route('pembayaran.struk', $payment) }}">Lihat struk</a>
                    <!-- <a class="btn-secondary buyer-cta-block" href="{{ route('pembayaran.show', $payment) }}">Lihat jejak HTTP</a> -->
                </div>
            @elseif ($payment->status === 'failed')
                <div class="pay-result-icon is-danger" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                </div>

                <h1 class="pay-result-title">Pembayaran Gagal</h1>
                <p class="pay-result-desc">Pesanan <strong>{{ $order->order_number }}</strong> masih menunggu pembayaran.</p>

                <dl class="admin-kv-list">
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Nomor pesanan</dt>
                        <dd class="admin-kv-row-val"><strong>{{ $order->order_number }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Total</dt>
                        <dd class="admin-kv-row-val"><strong class="admin-cell-price">{{ $order->formattedTotal() }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Metode</dt>
                        <dd class="admin-kv-row-val"><strong>{{ $method['label'] ?? $payment->payment_method }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Kode referensi</dt>
                        <dd class="admin-kv-row-val"><code class="admin-code">{{ $payment->reference_code }}</code></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Status pembayaran</dt>
                        <dd class="admin-kv-row-val">
                            <span class="status-chip status-chip-danger"><span>{{ $payment->statusLabel() }}</span></span>
                        </dd>
                    </div>
                </dl>

                @if ($order->status === 'menunggu_pembayaran' && now()->lessThan($order->payment_deadline_at))
                    <form method="POST" action="{{ route('pesanan.bayar-ulang', $order) }}" class="buyer-repay-form">
                        @csrf
                        <fieldset class="buyer-method-fieldset">
                            <legend class="admin-section-title">Pilih metode lain / bayar ulang</legend>
                            <p class="admin-hint">Pembayaran sebelumnya gagal; pembayaran baru memakai token dan referensi baru, batas waktu bayar tidak diperpanjang.</p>
                            <div class="buyer-method-grid">
                                @foreach ($methods as $m)
                                    <label class="buyer-method-card" for="hasil-{{ $m['code'] }}">
                                        <input type="radio" id="hasil-{{ $m['code'] }}" name="payment_method" value="{{ $m['code'] }}" @checked($loop->first)>
                                        <span class="buyer-method-body">
                                            <span class="buyer-method-title">{{ $m['label'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <button class="btn-primary buyer-cta-block" type="submit">Buat Pembayaran Baru</button>
                    </form>

                    <form method="POST" action="{{ route('pesanan.batal', $order) }}" class="buyer-cancel-form" onsubmit="return confirm('Batalkan pesanan {{ $order->order_number }}? Stok yang dicadangkan akan dilepas dan pembayaran dibatalkan.');">
                        @csrf
                        <button class="btn-danger-ghost buyer-cta-block" type="submit">Batalkan Pesanan</button>
                    </form>
                @else
                    <p class="admin-hint">Batas waktu bayar sudah terlewati atau pesanan tidak lagi menunggu pembayaran.</p>
                @endif

                <div class="pay-cta-stack">
                    <a class="btn-secondary buyer-cta-block" href="{{ route('pesanan.show', $order) }}">Lihat detail pesanan</a>
                    <a class="btn-secondary buyer-cta-block" href="{{ route('pembayaran.show', $payment) }}">Lihat jejak HTTP</a>
                </div>
            @else
                <div class="pay-result-icon is-warning" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>

                <h1 class="pay-result-title">Pembayaran Kedaluwarsa</h1>
                <p class="pay-result-desc">Pembayaran untuk pesanan <strong>{{ $order->order_number }}</strong> sudah melewati batas waktu bayar; pesanan berstatus <strong>{{ $order->statusLabel() }}</strong>.</p>

                <dl class="admin-kv-list">
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Nomor pesanan</dt>
                        <dd class="admin-kv-row-val"><strong>{{ $order->order_number }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Total</dt>
                        <dd class="admin-kv-row-val"><strong class="admin-cell-price">{{ $order->formattedTotal() }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Metode</dt>
                        <dd class="admin-kv-row-val"><strong>{{ $method['label'] ?? $payment->payment_method }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Kode referensi</dt>
                        <dd class="admin-kv-row-val"><code class="admin-code">{{ $payment->reference_code }}</code></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Status pesanan</dt>
                        <dd class="admin-kv-row-val">
                            <span class="status-chip status-chip-muted"><span>{{ $order->statusLabel() }}</span></span>
                        </dd>
                    </div>
                </dl>

                <div class="pay-cta-stack">
                    <a class="btn-primary buyer-cta-block" href="{{ route('katalog') }}">Kembali ke katalog</a>
                    <a class="btn-secondary buyer-cta-block" href="{{ route('pesanan.index') }}">Lihat riwayat pesanan</a>
                    <a class="btn-secondary buyer-cta-block" href="{{ route('pembayaran.show', $payment) }}">Lihat jejak HTTP</a>
                </div>
            @endif
        </div>
    </div>
@endsection
