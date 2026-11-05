@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Checkout</h1>
        <table>
            <thead><tr><th>Produk</th><th>Harga satuan</th><th>Jumlah</th><th>Subtotal</th></tr></thead>
            <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->product->formattedPrice() }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ 'Rp '.number_format($item->subtotalIdr(), 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p><strong>Total: {{ 'Rp '.number_format($total_idr, 0, ',', '.') }}</strong></p>
        <p>Batas waktu bayar setelah pesanan dibuat: {{ $expiry_minutes }} menit.</p>
        <form method="POST" action="{{ route('checkout.store') }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf
            <input type="hidden" name="checkout_key" value="{{ $checkout_key }}">
            <fieldset>
                <legend>Pilih metode pembayaran (Simulasi)</legend>
                @foreach ($methods as $method)
                    <div>
                        <input type="radio" id="metode-{{ $method['code'] }}" name="payment_method" value="{{ $method['code'] }}" @checked($loop->first)>
                        <label for="metode-{{ $method['code'] }}">{{ $method['label'] }}</label>
                    </div>
                @endforeach
            </fieldset>
            <p>Semua metode berlabel SIMULASI: tidak ada pembayaran riil di tahap ini.</p>
            <button class="btn-primary" type="submit">Buat Pesanan</button>
        </form>
        <p><a href="{{ route('keranjang.index') }}">Kembali ke keranjang</a></p>
    </div>
@endsection
