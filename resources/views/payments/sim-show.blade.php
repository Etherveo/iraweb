@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>SIMULASI PEMBAYARAN</h1>
        <p><strong>SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah dan tidak ada koneksi ke bank atau e-wallet.</p>
        @if ($message)
            <p class="alert">{{ $message }}</p>
        @endif
        <p>Nomor pesanan: <strong>{{ $order->order_number }}</strong></p>
        <p>Total: <strong>{{ $order->formattedTotal() }}</strong></p>
        <p>Metode: <strong>{{ $method['label'] ?? $payment->payment_method }}</strong> (Simulasi)</p>
        <p>Kode referensi: {{ $payment->reference_code }}</p>
        @if ($isVa)
            <p>Nomor VA (Simulasi): <strong>{{ $payment->formattedReference() }}</strong></p>
        @endif
        <h2>Item</h2>
        <ul>
        @foreach ($order->items as $item)
            <li>{{ $item->product_name_snapshot }} × {{ $item->quantity }} — {{ $item->formattedSubtotal() }}</li>
        @endforeach
        </ul>
        <p>Dibuat: {{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        <p>Kedaluwarsa: {{ $payment->expires_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        <p>Status pembayaran: <strong>{{ $payment->statusLabel() }}</strong></p>
        @if ($payment->status === 'pending' && now()->lessThan($payment->expires_at))
            <p>Sisa waktu: <span id="countdown" data-expires-at="{{ $expiresAtIso }}" data-server-now="{{ $serverNowIso }}">—</span></p>
            <p class="sr-only" aria-live="polite" id="countdown-aria"></p>
            <form method="POST" action="{{ route('pay.sim.bayar', $payment->payment_token) }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                @csrf
                <button class="btn-primary" type="submit">Bayar Sekarang (Simulasi)</button>
            </form>
            <form method="POST" action="{{ route('pay.sim.gagal', $payment->payment_token) }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                @csrf
                <button type="submit">Simulasikan Gagal</button>
            </form>
        @else
            <p>Tombol Bayar/Gagal tidak tersedia (pembayaran {{ $payment->statusLabel() }}).</p>
        @endif
        @auth('web')
            <p><a href="{{ route('pesanan.show', $order) }}">Kembali ke Pesanan</a></p>
        @else
            <p><a href="{{ route('masuk') }}">Kembali ke Pesanan</a></p>
        @endauth
        <p>Catatan: signature webhook pada simulasi ini adalah dummy, bukan signature gateway riil.</p>
    </div>
    @if ($payment->status === 'pending' && now()->lessThan($payment->expires_at))
        @include('payments._countdown')
    @endif
@endsection
