@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Struk — SIMULASI</h1>
        <p><strong>STRUK SIMULASI — bukan bukti pembayaran sah.</strong></p>
        <p>Ira Stationery — Gorontalo</p>
        <p>Nomor pesanan: <strong>{{ $order->order_number }}</strong></p>
        <p>Kode referensi: {{ $payment->reference_code }}</p>
        <p>Metode: {{ $method['label'] ?? $payment->payment_method }}</p>
        <p>Dibayar: {{ $payment->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        <h2>Item</h2>
        <ul>
        @foreach ($order->items as $item)
            <li>{{ $item->product_name_snapshot }} × {{ $item->quantity }} — {{ $item->formattedSubtotal() }}</li>
        @endforeach
        </ul>
        <p><strong>Total: {{ $order->formattedTotal() }}</strong></p>
        <p><strong>STRUK SIMULASI — bukan bukti pembayaran sah.</strong></p>
        <button type="button" onclick="window.print()">Cetak Struk</button>
        <p><a href="{{ route('pembayaran.hasil', $payment) }}">Kembali ke halaman hasil</a></p>
    </div>
@endsection
