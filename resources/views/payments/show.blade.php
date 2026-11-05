@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Detail Pembayaran — SIMULASI</h1>
        <p><strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah.</p>
        <p>Pesanan: <strong><a href="{{ route('pesanan.show', $order) }}">{{ $order->order_number }}</a></strong></p>
        <p>Status pesanan: {{ $order->statusLabel() }}</p>
        <p>Metode: <strong>{{ $method['label'] ?? $payment->payment_method }}</strong></p>
        <p>Status pembayaran: <strong>{{ $payment->statusLabel() }}</strong></p>
        <p>Nominal: <strong>{{ $payment->formattedAmount() }}</strong></p>
        <p>Kode referensi: {{ $payment->reference_code }}</p>
        <p>Dibuat: {{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        <p>Kedaluwarsa: {{ $payment->expires_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        @if ($payment->paid_at)
            <p>Dibayar: {{ $payment->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        @endif
        <p>Asal data: {{ $payment->origin === 'developer_simulation' ? 'SIMULASI (panel developer)' : 'Normal' }}</p>
        @if ($payment->isFinal())
            <p><a href="{{ route('pembayaran.hasil', $payment) }}">Lihat halaman hasil</a></p>
        @else
            <p><a href="{{ route('pesanan.bayar', $order) }}">Lanjutkan pembayaran (Simulasi)</a></p>
        @endif
        @if ($payment->status === 'paid')
            <p><a href="{{ route('pembayaran.struk', $payment) }}">Lihat struk</a></p>
        @endif
    </div>
    @include('payments._panel', ['payment' => $payment, 'panel' => $panel])
    <p><a href="{{ route('pembayaran.index') }}">Kembali ke riwayat pembayaran</a></p>
@endsection
