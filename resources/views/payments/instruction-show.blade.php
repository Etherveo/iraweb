@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 7 (UI.md §9.3, §10, §16, referensi Figma 3:104151 & 3:104319).
         QR client-side ([data-qr-url]), fallback teks server, nomor VA & tombol Salin,
         hitung mundur (#countdown), polling, dan peringatan loopback dipertahankan. --}}
    <div class="admin-page">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('pesanan.show', $order) }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke detail pesanan</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Pembayaran</p>
                <h1 class="admin-title">Pembayaran</h1>
                <p class="admin-subtitle">Ikuti instruksi di bawah ini untuk menyelesaikan pembayaran pesananmu.</p>
            </div>
            <div class="admin-head-actions">
                <span class="status-chip status-chip-warning">
                    <span>{{ $payment->statusLabel() }}</span>
                </span>
            </div>
        </header>

        <div class="buyer-split-layout">
            <div class="card admin-panel">
                <div class="pay-method-banner">
                    <div>
                        <p class="admin-eyebrow">Metode Pembayaran</p>
                        <h2 class="admin-section-title" style="margin:0">{{ $method['label'] ?? $payment->payment_method }}</h2>
                    </div>
                </div>

                @if (($method['kind'] ?? 'qr') === 'va')
                    <div class="pay-va-box">
                        <h3 class="pay-sub-heading">Nomor VA</h3>
                        <p class="pay-sim-caption"><strong>Nomor VA — bukan nomor rekening.</strong></p>
                        <div class="pay-va-row">
                            <p class="va-number"><strong>{{ $payment->formattedReference() }}</strong></p>
                            <button type="button" class="btn-secondary" onclick="navigator.clipboard.writeText('{{ $payment->reference_code }}').then(() => { this.textContent = 'Tersalin'; });">Salin</button>
                        </div>
                    </div>

                    <div class="pay-qr-section">
                        <h3 class="pay-sub-heading">Kode QR pendamping</h3>
                        <p class="admin-hint">Pindai untuk membuka halaman pembayaran:</p>
                        <div class="qr-small pay-qr-frame" data-qr-url="{{ $payment->qr_url }}">
                            <img class="qr-image" alt="Kode QR pendamping pembayaran" hidden>
                            {!! $qrHtml !!}
                        </div>
                    </div>
                @else
                    <div class="pay-qr-section">
                        <h3 class="pay-sub-heading">Kode QR</h3>
                        <div class="qr-large pay-qr-frame" data-qr-url="{{ $payment->qr_url }}">
                            <img class="qr-image" alt="Kode QR pembayaran" width="240" height="240" hidden>
                            {!! $qrHtml !!}
                        </div>
                        <p class="pay-url-fallback">Atau buka tautan ini: <a class="admin-cell-link" href="{{ $payment->qr_url }}">{{ $payment->qr_url }}</a></p>
                    </div>
                @endif

                <div class="pay-cta-stack">
                    <a class="btn-primary buyer-cta-block" href="{{ $payment->qr_url }}" target="_blank" rel="noopener">Buka Halaman Pembayaran</a>
                    <a class="btn-secondary buyer-cta-block" href="{{ route('pesanan.show', $order) }}">Kembali ke detail pesanan</a>
                </div>

                @if ($isLoopback)
                    <div class="pay-loopback-box">
                        <p><strong>Perhatian:</strong> alamat aplikasi ini adalah loopback (localhost), jadi QR tidak dapat dibuka dari perangkat lain. Gunakan tombol di atas dari perangkat ini.</p>
                    </div>
                @endif
            </div>

            <aside class="card admin-panel buyer-summary-card" aria-label="Ringkasan pembayaran">
                <h2 class="admin-section-title">Ringkasan pembayaran</h2>
                <dl class="admin-kv-list">
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Nama toko</dt>
                        <dd class="admin-kv-row-val"><strong>Ira Stationery</strong></dd>
                    </div>
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
                        <dt class="admin-kv-row-label">Dibuat</dt>
                        <dd class="admin-kv-row-val">{{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Kedaluwarsa</dt>
                        <dd class="admin-kv-row-val">{{ $payment->expires_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                    </div>
                </dl>

                @include('payments._pickup-instruction', ['order' => $order])

                <div class="pay-countdown-card">
                    <span class="pay-countdown-label">Sisa waktu bayar</span>
                    <strong class="pay-countdown-timer" id="countdown" data-expires-at="{{ $expiresAtIso }}" data-server-now="{{ $serverNowIso }}" data-poll-url="{{ route('pembayaran.status', $payment) }}">—</strong>
                    <p class="sr-only" aria-live="polite" id="countdown-aria"></p>
                </div>
            </aside>
        </div>
    </div>
    @include('payments._countdown')
@endsection
