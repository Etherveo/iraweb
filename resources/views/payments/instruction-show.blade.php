@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Pembayaran — SIMULASI</h1>
        <p><strong>SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah dan tidak ada koneksi ke bank atau e-wallet; ini hanya simulasi untuk keperluan analitik toko.</p>
        <p>Nama toko: <strong>Ira Stationery</strong></p>
        <p>Nomor pesanan: <strong>{{ $order->order_number }}</strong></p>
        <p>Total: <strong>{{ $order->formattedTotal() }}</strong></p>
        <p>Metode: <strong>{{ $method['label'] ?? $payment->payment_method }}</strong></p>
        <p>Kode referensi: {{ $payment->reference_code }}</p>
        <p>Dibuat: {{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        <p>Kedaluwarsa: {{ $payment->expires_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>

        @if (($method['kind'] ?? 'qr') === 'va')
            <h2>Nomor VA (Simulasi)</h2>
            <p><strong>Nomor VA SIMULASI — bukan nomor rekening.</strong></p>
            <p class="va-number"><strong>{{ $payment->formattedReference() }}</strong></p>
            <button type="button" onclick="navigator.clipboard.writeText('{{ $payment->reference_code }}').then(() => { this.textContent = 'Tersalin'; });">Salin</button>
            <h3>Kode QR pendamping</h3>
            <p>Pindai untuk membuka halaman simulasi:</p>
            <div class="qr-small" data-qr-url="{{ $payment->qr_url }}"><img class="qr-image" alt="Kode QR pendamping pembayaran simulasi" hidden>{!! $qrHtml !!}</div>
        @else
            <h2>Kode QR (Simulasi)</h2>
            <p><strong>SIMULASI — bukan QR pembayaran riil.</strong></p>
            <div class="qr-large" data-qr-url="{{ $payment->qr_url }}"><img class="qr-image" alt="Kode QR pembayaran simulasi" width="240" height="240" hidden>{!! $qrHtml !!}</div>
            <p>Atau buka tautan ini: <a href="{{ $payment->qr_url }}">{{ $payment->qr_url }}</a></p>
        @endif

        <p>Sisa waktu bayar: <span id="countdown" data-expires-at="{{ $expiresAtIso }}" data-server-now="{{ $serverNowIso }}" data-poll-url="{{ route('pembayaran.status', $payment) }}">—</span></p>
        <p class="sr-only" aria-live="polite" id="countdown-aria"></p>

        <p><a href="{{ $payment->qr_url }}" target="_blank" rel="noopener">Buka Halaman Simulasi Pembayaran</a></p>
        @if ($isLoopback)
            <p><strong>Perhatian:</strong> alamat aplikasi ini adalah loopback (localhost), jadi QR tidak dapat dibuka dari perangkat lain. Gunakan tombol di atas dari perangkat ini.</p>
        @endif

        <p>Catatan: signature webhook pada simulasi ini adalah dummy, bukan signature gateway riil.</p>
        <p><a href="{{ route('pesanan.show', $order) }}">Kembali ke detail pesanan</a></p>
    </div>
    @include('payments._countdown')
@endsection
