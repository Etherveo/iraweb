@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Detail Pembayaran — SIMULASI</h1>
        <p><strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Halaman ini hanya-baca: admin tidak dapat mengubah status pembayaran.</p>
        <p>Pesanan: <strong><a href="{{ route('admin.pesanan.show', $order) }}">{{ $order->order_number }}</a></strong></p>
        <p>Pembeli: {{ $payment->user->name }} ({{ $payment->user->email }})</p>
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
    </div>
    @include('payments._panel', ['payment' => $payment, 'panel' => $panel])
    <p><a href="{{ route('admin.pembayaran.index') }}">Kembali ke daftar pembayaran</a></p>
@endsection
