@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4b (UI.md §6.1, §9.4, §10.1 + panel teknis tersembunyi
         khusus halaman detail pembayaran admin). Panel (_panel.blade.php)
         tetap berada di DOM di dalam popup tersembunyi agar tes PAY-37..42/52/54
         tetap hijau tanpa diubah. --}}
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
            <a class="admin-back-link" href="{{ route('admin.pembayaran.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke daftar pembayaran</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Transaksi &bull; Pembayaran</p>
                <h1 class="admin-title">Detail Pembayaran — SIMULASI</h1>
                <p class="admin-subtitle">Ringkasan pembayaran simulasi dan status pesanan terkait.</p>
            </div>
            <div class="admin-head-actions">
                <span class="status-chip {{ $paymentChips[$payment->status] ?? 'status-chip-muted' }}">
                    <span>{{ $payment->statusLabel() }}</span>
                </span>
            </div>
        </header>

        <div class="admin-sim-banner" data-trace-trigger="admin-trace-popup">
            <strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Halaman ini hanya-baca: admin tidak dapat mengubah status pembayaran.
        </div>

        <div class="card admin-panel">
            <h2 class="admin-section-title">Ringkasan pembayaran</h2>
            <div class="admin-kv-groups">
                <section class="admin-kv-section" aria-labelledby="kv-group-pesanan">
                    <h3 class="admin-kv-group-title" id="kv-group-pesanan">Pesanan</h3>
                    <dl class="admin-kv-list">
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Pesanan</dt>
                            <dd class="admin-kv-row-val">
                                <a class="admin-cell-link" href="{{ route('admin.pesanan.show', $order) }}">{{ $order->order_number }}</a>
                            </dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Pembeli</dt>
                            <dd class="admin-kv-row-val">{{ $payment->user->name }} ({{ $payment->user->email }})</dd>
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

                <section class="admin-kv-section" aria-labelledby="kv-group-pembayaran">
                    <h3 class="admin-kv-group-title" id="kv-group-pembayaran">Pembayaran</h3>
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

                <section class="admin-kv-section" aria-labelledby="kv-group-waktu">
                    <h3 class="admin-kv-group-title" id="kv-group-waktu">Waktu (WITA)</h3>
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
                <a class="btn-secondary" href="{{ route('admin.pembayaran.index') }}">Kembali ke daftar pembayaran</a>
                <a class="btn-secondary" href="{{ route('admin.pesanan.show', $order) }}">Lihat pesanan</a>
            </div>
        </div>

        <div class="trace-popup" id="admin-trace-popup" hidden>
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
