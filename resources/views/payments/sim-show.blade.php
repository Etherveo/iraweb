@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 7 (UI.md §9.3, §10, §16 + panel teknis tersembunyi).
         Tanpa data pribadi pembeli (PAY-15). Panel (_panel.blade.php) tetap di DOM
         di dalam #sim-trace-popup[hidden], dibuka lewat ketuk 5x pada .admin-sim-banner. --}}
    @php
        $paymentChips = [
            'pending' => 'status-chip-warning',
            'paid' => 'status-chip-success',
            'failed' => 'status-chip-danger',
            'expired' => 'status-chip-muted',
            'cancelled' => 'status-chip-muted',
        ];
    @endphp
    <div class="admin-page admin-page-narrow">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Gateway Simulator &bull; Token Pembayaran</p>
                <h1 class="admin-title">SIMULASI PEMBAYARAN</h1>
                <p class="admin-subtitle">Halaman simulasi pemegang token untuk memicu pembayaran atau kegagalan.</p>
            </div>
            <div class="admin-head-actions">
                <span class="status-chip {{ $paymentChips[$payment->status] ?? 'status-chip-muted' }}">
                    <span>{{ $payment->statusLabel() }}</span>
                </span>
            </div>
        </header>

        <div class="admin-sim-banner" data-trace-trigger="sim-trace-popup">
            <strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah dan tidak ada koneksi ke bank atau e-wallet.
        </div>

        @if ($message)
            <div class="alert pay-sim-alert">{{ $message }}</div>
        @endif

        <div class="card admin-panel">
            <h2 class="admin-section-title">Rincian tagihan simulasi</h2>
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
                @if ($isVa)
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Nomor VA (Simulasi)</dt>
                        <dd class="admin-kv-row-val"><strong class="va-number-inline">{{ $payment->formattedReference() }}</strong></dd>
                    </div>
                @endif
                <div class="admin-kv-row">
                    <dt class="admin-kv-row-label">Dibuat</dt>
                    <dd class="admin-kv-row-val">{{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                </div>
                <div class="admin-kv-row">
                    <dt class="admin-kv-row-label">Kedaluwarsa</dt>
                    <dd class="admin-kv-row-val">{{ $payment->expires_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                </div>
                <div class="admin-kv-row">
                    <dt class="admin-kv-row-label">Status pembayaran</dt>
                    <dd class="admin-kv-row-val"><strong>{{ $payment->statusLabel() }}</strong></dd>
                </div>
            </dl>

            <h3 class="admin-kv-group-title">Item</h3>
            <ul class="buyer-summary-lines pay-sim-items">
                @foreach ($order->items as $item)
                    <li>
                        <span>{{ $item->product_name_snapshot }} &times; {{ $item->quantity }}</span>
                        <strong>{{ $item->formattedSubtotal() }}</strong>
                    </li>
                @endforeach
            </ul>

            @if ($payment->status === 'pending' && now()->lessThan($payment->expires_at))
                <div class="pay-countdown-card">
                    <span class="pay-countdown-label">Sisa waktu</span>
                    <strong class="pay-countdown-timer" id="countdown" data-expires-at="{{ $expiresAtIso }}" data-server-now="{{ $serverNowIso }}">—</strong>
                    <p class="sr-only" aria-live="polite" id="countdown-aria"></p>
                </div>

                <div class="pay-sim-actions">
                    <form method="POST" action="{{ route('pay.sim.bayar', $payment->payment_token) }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                        @csrf
                        <button class="btn-primary buyer-cta-block" type="submit">Bayar Sekarang (Simulasi)</button>
                    </form>
                    <form method="POST" action="{{ route('pay.sim.gagal', $payment->payment_token) }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                        @csrf
                        <button class="btn-danger-ghost buyer-cta-block" type="submit">Simulasikan Gagal</button>
                    </form>
                </div>
            @else
                <p class="pay-sim-closed">Tombol Bayar/Gagal tidak tersedia (pembayaran {{ $payment->statusLabel() }}).</p>
            @endif

            <div class="admin-form-actions">
                @auth('web')
                    <a class="btn-secondary" href="{{ route('pesanan.show', $order) }}">Kembali ke Pesanan</a>
                @else
                    <a class="btn-secondary" href="{{ route('masuk') }}">Kembali ke Pesanan</a>
                @endauth
            </div>

            <p class="buyer-summary-note">Catatan: signature webhook pada simulasi ini adalah dummy, bukan signature gateway riil.</p>
        </div>

        <div class="trace-popup" id="sim-trace-popup" hidden>
            <div class="trace-popup-backdrop" data-trace-close></div>
            <div class="trace-popup-dialog" role="dialog" aria-modal="true" aria-label="Detail teknis pembayaran simulasi" tabindex="-1">
                <button type="button" class="trace-popup-x" data-trace-close aria-label="Tutup">×</button>

                <div class="trace-popup-gate" data-trace-stage="gate">
                    <button type="button" class="btn-primary" data-trace-expand>Lihat detail teknis</button>
                </div>

                <div class="trace-popup-content" data-trace-stage="panel" hidden>
                    @include('payments._panel', ['payment' => $payment, 'panel' => $panel])
                </div>
            </div>
        </div>
    </div>

    @if ($payment->status === 'pending' && now()->lessThan($payment->expires_at))
        @include('payments._countdown')
    @endif
@endsection
