@extends('layouts.app')

@section('content')
    <div class="card">
        @if ($payment->status === 'paid')
            <h1>⚠ Pembayaran Berhasil — SIMULASI</h1>
            <p><strong>SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah.</p>
            <p>Pesanan <strong>{{ $order->order_number }}</strong> sudah dibayar (simulasi).</p>
            <p>Total: <strong>{{ $order->formattedTotal() }}</strong></p>
            <p>Metode: {{ $method['label'] ?? $payment->payment_method }}</p>
            <p>Dibayar: {{ $payment->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</p>
            <p><a href="{{ route('pembayaran.show', $payment) }}">Lihat jejak HTTP</a></p>
            <p><a href="{{ route('pembayaran.struk', $payment) }}">Lihat struk</a></p>
            <p><a href="{{ route('pesanan.show', $order) }}">Lihat detail pesanan</a></p>
        @elseif ($payment->status === 'failed')
            <h1>⚠ Pembayaran Gagal — SIMULASI</h1>
            <p><strong>SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah; pembayaran simulasi ini gagal.</p>
            <p>Pesanan <strong>{{ $order->order_number }}</strong> masih menunggu pembayaran.</p>
            <p>Total: <strong>{{ $order->formattedTotal() }}</strong></p>
            @if ($order->status === 'menunggu_pembayaran' && now()->lessThan($order->payment_deadline_at))
                <form method="POST" action="{{ route('pesanan.bayar-ulang', $order) }}">
                    @csrf
                    <fieldset>
                        <legend>Pilih metode lain / bayar ulang (Simulasi)</legend>
                        @foreach ($methods as $m)
                            <div>
                                <input type="radio" id="hasil-{{ $m['code'] }}" name="payment_method" value="{{ $m['code'] }}" @checked($loop->first)>
                                <label for="hasil-{{ $m['code'] }}">{{ $m['label'] }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                    <p>Pembayaran sebelumnya gagal; pembayaran baru memakai token dan referensi baru, batas waktu bayar tidak diperpanjang.</p>
                    <button type="submit">Buat Pembayaran Baru</button>
                </form>
                <form method="POST" action="{{ route('pesanan.batal', $order) }}" onsubmit="return confirm('Batalkan pesanan {{ $order->order_number }}? Stok yang dicadangkan akan dilepas dan pembayaran dibatalkan.');">
                    @csrf
                    <button type="submit">Batalkan Pesanan</button>
                </form>
            @else
                <p>Batas waktu bayar sudah terlewati atau pesanan tidak lagi menunggu pembayaran.</p>
            @endif
            <p><a href="{{ route('pembayaran.show', $payment) }}">Lihat jejak HTTP</a></p>
            <p><a href="{{ route('pesanan.show', $order) }}">Lihat detail pesanan</a></p>
        @else
            <h1>⚠ Pembayaran Kedaluwarsa — SIMULASI</h1>
            <p><strong>SIMULASI — bukan pembayaran riil.</strong></p>
            <p>Pembayaran untuk pesanan <strong>{{ $order->order_number }}</strong> sudah melewati batas waktu bayar; pesanan berstatus <strong>{{ $order->statusLabel() }}</strong>.</p>
            <p>Total: <strong>{{ $order->formattedTotal() }}</strong></p>
            <p><a href="{{ route('pesanan.index') }}">Lihat riwayat pesanan</a></p>
            <p><a href="{{ route('katalog') }}">Kembali ke katalog</a></p>
            <p><a href="{{ route('pembayaran.show', $payment) }}">Lihat jejak HTTP</a></p>
        @endif
    </div>
@endsection
