@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Pesanan {{ $order->order_number }}</h1>
        <p>Status: <strong>{{ $order->statusLabel() }}</strong></p>
        <p>Dibuat: {{ $order->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        <p>Batas waktu bayar: {{ $order->payment_deadline_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        @if ($order->paid_at)
            <p>Dibayar: {{ $order->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
        @endif
        <h2>Item</h2>
        <table>
            <thead><tr><th>Produk</th><th>Harga satuan</th><th>Jumlah</th><th>Subtotal</th></tr></thead>
            <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td>{{ $item->formattedUnitPrice() }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->formattedSubtotal() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p><strong>Total: {{ $order->formattedTotal() }}</strong></p>
        <h2>Pembayaran (Simulasi)</h2>
        @if ($order->payments->isEmpty())
            <p>Belum ada pembayaran.</p>
        @else
            @foreach ($order->payments as $payment)
                <p>Metode: {{ config('payment.methods.'.$payment->payment_method.'.label', $payment->payment_method) }}</p>
                <p>Status: {{ $payment->statusLabel() }}</p>
                <p>Nominal: {{ $payment->formattedAmount() }}</p>
                <p>Kode referensi: {{ $payment->reference_code }}</p>
                <p>Kedaluwarsa: {{ $payment->expires_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
            @endforeach
        @endif
        <h2>Riwayat Status</h2>
        @if ($order->histories->isEmpty())
            <p>Belum ada riwayat status.</p>
        @else
            <ul>
            @foreach ($order->histories->sortByDesc('occurred_at') as $history)
                <li>{{ $history->from_status ?? '—' }} → {{ $history->to_status }} ({{ $history->trigger }}) — {{ $history->occurred_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA @if ($history->reason) — alasan: {{ $history->reason }} @endif</li>
            @endforeach
            </ul>
        @endif
        @if ($order->status === 'menunggu_pembayaran')
            @php
                $pendingPayment = $order->pendingPayment();
                $failedPayment = $order->payments->where('status', 'failed')->sortByDesc('created_at')->first();
            @endphp
            @if ($pendingPayment)
                <p><a class="btn-primary" href="{{ route('pesanan.bayar', $order) }}">Lanjutkan Pembayaran (Simulasi)</a></p>
            @elseif ($failedPayment && now()->lessThan($order->payment_deadline_at))
                <form method="POST" action="{{ route('pesanan.bayar-ulang', $order) }}">
                    @csrf
                    <fieldset>
                        <legend>Bayar ulang / pilih metode lain (Simulasi)</legend>
                        @foreach ($methods as $method)
                            <div>
                                <input type="radio" id="ulang-{{ $method['code'] }}" name="payment_method" value="{{ $method['code'] }}" @checked($loop->first)>
                                <label for="ulang-{{ $method['code'] }}">{{ $method['label'] }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                    <p>Pembayaran sebelumnya gagal; pembayaran baru memakai token dan referensi baru, batas waktu bayar tidak diperpanjang.</p>
                    <button type="submit">Buat Pembayaran Baru</button>
                </form>
            @endif
            <form method="POST" action="{{ route('pesanan.batal', $order) }}" onsubmit="return confirm('Batalkan pesanan {{ $order->order_number }}? Stok yang dicadangkan akan dilepas dan pembayaran dibatalkan.');">
                @csrf
                <button type="submit">Batalkan Pesanan</button>
            </form>
        @endif
        <p><a href="{{ route('pesanan.index') }}">Kembali ke daftar pesanan</a></p>
    </div>
@endsection
