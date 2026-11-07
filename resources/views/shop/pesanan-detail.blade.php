@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 6 (UI.md §2.4, §6, §9.2, §9.3, §16, referensi Figma 3:104844).
         Semua informasi pesanan, item, pembayaran simulasi, riwayat status,
         tautan lanjutkan pembayaran, form bayar ulang, dan form batal dipertahankan. --}}
    @php
        $orderChips = [
            'menunggu_pembayaran' => 'status-chip-warning',
            'dibayar' => 'status-chip-success',
            'diproses' => 'status-chip-info',
            'dikirim' => 'status-chip-info',
            'selesai' => 'status-chip-success',
            'dibatalkan' => 'status-chip-danger',
            'kedaluwarsa' => 'status-chip-muted',
        ];
        $paymentChips = [
            'pending' => 'status-chip-warning',
            'paid' => 'status-chip-success',
            'failed' => 'status-chip-danger',
            'expired' => 'status-chip-muted',
            'cancelled' => 'status-chip-muted',
        ];
    @endphp
    <div class="admin-page">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('pesanan.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke daftar pesanan</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Pesanan</p>
                <h1 class="admin-title">Pesanan {{ $order->order_number }}</h1>
                <p class="admin-subtitle">Rincian barang pesanan, pembayaran, dan linimasa status.</p>
            </div>
            <div class="admin-head-actions">
                <span class="status-chip {{ $orderChips[$order->status] ?? 'status-chip-muted' }}">
                    <span>{{ $order->statusLabel() }}</span>
                </span>
            </div>
        </header>

        <div class="buyer-split-layout">
            <div class="buyer-main-stack">
                <div class="card admin-panel">
                    <h2 class="admin-section-title">Ringkasan &amp; Item</h2>
                    <dl class="admin-kv-list">
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Status</dt>
                            <dd class="admin-kv-row-val"><strong>{{ $order->statusLabel() }}</strong></dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Dibuat</dt>
                            <dd class="admin-kv-row-val">{{ $order->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Batas waktu bayar</dt>
                            <dd class="admin-kv-row-val">{{ $order->payment_deadline_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                        </div>
                        @if ($order->paid_at)
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Dibayar</dt>
                                <dd class="admin-kv-row-val">{{ $order->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                            </div>
                        @endif
                    </dl>

                    <h3 class="admin-kv-group-title">Item</h3>
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
                            @foreach ($order->items as $item)
                                <tr>
                                    <td data-label="Produk" class="admin-cell-primary">{{ $item->product_name_snapshot }}</td>
                                    <td data-label="Harga satuan" class="admin-cell-nowrap">{{ $item->formattedUnitPrice() }}</td>
                                    <td data-label="Jumlah">{{ $item->quantity }}</td>
                                    <td data-label="Subtotal" class="admin-cell-price">{{ $item->formattedSubtotal() }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="admin-order-total"><strong>Total: {{ $order->formattedTotal() }}</strong></p>
                </div>

                <div class="card admin-panel">
                    <h2 class="admin-section-title">Pembayaran</h2>
                    @if ($order->payments->isEmpty())
                        <div class="admin-empty">
                            <p class="admin-empty-title">Belum ada pembayaran.</p>
                        </div>
                    @else
                        <div class="admin-stack">
                            @foreach ($order->payments as $payment)
                                <div class="admin-subcard">
                                    <div class="buyer-payment-head">
                                        <div>
                                            <strong class="admin-cell-primary">{{ config('payment.methods.'.$payment->payment_method.'.label', $payment->payment_method) }}</strong>
                                        </div>
                                        <span class="status-chip {{ $paymentChips[$payment->status] ?? 'status-chip-muted' }}">
                                            <span>{{ $payment->statusLabel() }}</span>
                                        </span>
                                    </div>
                                    <dl class="buyer-payment-meta">
                                        <div>
                                            <dt>Nominal</dt>
                                            <dd class="admin-cell-price">{{ $payment->formattedAmount() }}</dd>
                                        </div>
                                        <div>
                                            <dt>Kode referensi</dt>
                                            <dd><code class="admin-code">{{ $payment->reference_code }}</code></dd>
                                        </div>
                                        <div>
                                            <dt>Kedaluwarsa</dt>
                                            <dd>{{ $payment->expires_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                                        </div>
                                    </dl>
                                    <p class="buyer-payment-foot">
                                        <a class="admin-action-link" href="{{ route('pembayaran.show', $payment) }}">Lihat detail pembayaran &amp; jejak</a>
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($order->status === 'menunggu_pembayaran')
                        @php
                            $pendingPayment = $order->pendingPayment();
                            $failedPayment = $order->payments->where('status', 'failed')->sortByDesc('created_at')->first();
                        @endphp
                        @if ($pendingPayment)
                            <div class="buyer-order-actions">
                                <a class="btn-primary buyer-cta-block" href="{{ route('pesanan.bayar', $order) }}">Lanjutkan Pembayaran</a>
                            </div>
                        @elseif ($failedPayment && now()->lessThan($order->payment_deadline_at))
                            <form method="POST" action="{{ route('pesanan.bayar-ulang', $order) }}" class="buyer-repay-form">
                                @csrf
                                <fieldset class="buyer-method-fieldset">
                                    <legend class="admin-section-title">Bayar ulang / pilih metode lain</legend>
                                    <p class="admin-hint">Pembayaran sebelumnya gagal; pembayaran baru memakai token dan referensi baru, batas waktu bayar tidak diperpanjang.</p>
                                    <div class="buyer-method-grid">
                                        @foreach ($methods as $method)
                                            <label class="buyer-method-card" for="ulang-{{ $method['code'] }}">
                                                <input type="radio" id="ulang-{{ $method['code'] }}" name="payment_method" value="{{ $method['code'] }}" @checked($loop->first)>
                                                <span class="buyer-method-body">
                                                    <span class="buyer-method-title">{{ $method['label'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <button class="btn-primary" type="submit">Buat Pembayaran Baru</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('pesanan.batal', $order) }}" class="buyer-cancel-form" onsubmit="return confirm('Batalkan pesanan {{ $order->order_number }}? Stok yang dicadangkan akan dilepas dan pembayaran dibatalkan.');">
                            @csrf
                            <button class="btn-danger-ghost" type="submit">Batalkan Pesanan</button>
                        </form>
                    @endif
                </div>
            </div>

            <aside class="card admin-panel" aria-label="Riwayat status pesanan">
                <h2 class="admin-section-title">Riwayat Status</h2>
                @if ($order->histories->isEmpty())
                    <div class="admin-empty">
                        <p class="admin-empty-title">Belum ada riwayat status.</p>
                    </div>
                @else
                    <ul class="admin-timeline">
                    @foreach ($order->histories->sortByDesc('occurred_at') as $history)
                        <li>
                            <strong>{{ $history->from_status ?? '—' }} → {{ $history->to_status }}</strong>
                            ({{ $history->trigger }}) — {{ $history->occurred_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA
                            @if ($history->reason)
                                — alasan: {{ $history->reason }}
                            @endif
                        </li>
                    @endforeach
                    </ul>
                @endif

                <div class="admin-form-actions">
                    <a class="btn-secondary" href="{{ route('pesanan.index') }}">Kembali ke daftar pesanan</a>
                </div>
            </aside>
        </div>
    </div>
@endsection
