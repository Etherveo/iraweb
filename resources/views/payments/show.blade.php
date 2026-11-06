@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 7 (UI.md §6.1, §9.3, §10.1 + panel teknis tersembunyi
         pembeli, referensi Figma 3:105449). Panel (_panel.blade.php) tetap di DOM
         di dalam #buyer-trace-popup[hidden] agar tes PAY-37..44/50/54 tetap hijau. --}}
    @php
        $paymentChips = [
            'pending' => 'status-chip-warning',
            'paid' => 'status-chip-success',
            'failed' => 'status-chip-danger',
            'expired' => 'status-chip-muted',
            'cancelled' => 'status-chip-muted',
        ];
        $orderChips = [
            'menunggu_pembayaran' => 'status-chip-warning',
            'dibayar' => 'status-chip-success',
            'diproses' => 'status-chip-info',
            'dikirim' => 'status-chip-info',
            'selesai' => 'status-chip-success',
            'dibatalkan' => 'status-chip-danger',
            'kedaluwarsa' => 'status-chip-muted',
        ];
    @endphp
    <div class="admin-page admin-page-narrow">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('pembayaran.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke riwayat pembayaran</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Pembayaran</p>
                <h1 class="admin-title">Detail Pembayaran — SIMULASI</h1>
                <p class="admin-subtitle">Ringkasan pembayaran simulasi dan status pesanan terkait.</p>
            </div>
            <div class="admin-head-actions">
                <span class="status-chip {{ $paymentChips[$payment->status] ?? 'status-chip-muted' }}">
                    <span>{{ $payment->statusLabel() }}</span>
                </span>
            </div>
        </header>

        <div class="admin-sim-banner" data-trace-trigger="buyer-trace-popup">
            <strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah.
        </div>

        <div class="card admin-panel">
            <h2 class="admin-section-title">Ringkasan pembayaran</h2>
            <div class="admin-kv-groups">
                <section class="admin-kv-section" aria-labelledby="buyer-kv-pesanan">
                    <h3 class="admin-kv-group-title" id="buyer-kv-pesanan">Pesanan</h3>
                    <dl class="admin-kv-list">
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Pesanan</dt>
                            <dd class="admin-kv-row-val">
                                <a class="admin-cell-link" href="{{ route('pesanan.show', $order) }}">{{ $order->order_number }}</a>
                            </dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Status pesanan</dt>
                            <dd class="admin-kv-row-val">
                                <span class="status-chip {{ $orderChips[$order->status] ?? 'status-chip-muted' }}">
                                    <span>{{ $order->statusLabel() }}</span>
                                </span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-kv-section" aria-labelledby="buyer-kv-pembayaran">
                    <h3 class="admin-kv-group-title" id="buyer-kv-pembayaran">Pembayaran</h3>
                    <dl class="admin-kv-list">
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Metode</dt>
                            <dd class="admin-kv-row-val"><strong>{{ $method['label'] ?? $payment->payment_method }}</strong></dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Status pembayaran</dt>
                            <dd class="admin-kv-row-val">
                                <span class="status-chip {{ $paymentChips[$payment->status] ?? 'status-chip-muted' }}">
                                    <span>{{ $payment->statusLabel() }}</span>
                                </span>
                            </dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Nominal</dt>
                            <dd class="admin-kv-row-val"><strong class="admin-cell-price">{{ $payment->formattedAmount() }}</strong></dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Kode referensi</dt>
                            <dd class="admin-kv-row-val"><code class="admin-code">{{ $payment->reference_code }}</code></dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Asal data</dt>
                            <dd class="admin-kv-row-val">
                                @if ($payment->origin === 'developer_simulation')
                                    <span class="badge-simulasi">SIMULASI (panel developer)</span>
                                @else
                                    Normal
                                @endif
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-kv-section" aria-labelledby="buyer-kv-waktu">
                    <h3 class="admin-kv-group-title" id="buyer-kv-waktu">Waktu (WITA)</h3>
                    <dl class="admin-kv-list">
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Dibuat</dt>
                            <dd class="admin-kv-row-val">{{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Kedaluwarsa</dt>
                            <dd class="admin-kv-row-val">{{ $payment->expires_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                        </div>
                        @if ($payment->paid_at)
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Dibayar</dt>
                                <dd class="admin-kv-row-val">{{ $payment->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            </div>

            <div class="admin-form-actions">
                @if ($payment->isFinal())
                    <a class="btn-primary" href="{{ route('pembayaran.hasil', $payment) }}">Lihat halaman hasil</a>
                @else
                    <a class="btn-primary" href="{{ route('pesanan.bayar', $order) }}">Lanjutkan pembayaran (Simulasi)</a>
                @endif
                @if ($payment->status === 'paid')
                    <a class="btn-secondary" href="{{ route('pembayaran.struk', $payment) }}">Lihat struk</a>
                @endif
                <a class="btn-secondary" href="{{ route('pembayaran.index') }}">Kembali ke riwayat pembayaran</a>
                <a class="btn-secondary" href="{{ route('pesanan.show', $order) }}">Lihat pesanan</a>
            </div>
        </div>

        <div class="trace-popup" id="buyer-trace-popup" hidden>
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
@endsection
