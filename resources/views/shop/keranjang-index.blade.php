@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Keranjang</h1>
        @if ($items->isEmpty())
            <p>Keranjang masih kosong.</p>
            <p><a href="{{ route('katalog') }}">Lihat katalog</a> untuk mulai berbelanja.</p>
        @else
            <table>
                <thead><tr><th>Produk</th><th>Harga satuan</th><th>Jumlah</th><th>Subtotal</th><th>Aksi</th></tr></thead>
                <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>
                            <a href="{{ route('produk.show', $item->product) }}">{{ $item->product->name }}</a>
                            @if ($item->isBlocked())
                                <p><strong>Perhatian:</strong> {{ $item->blockedReason() }}</p>
                            @endif
                        </td>
                        <td>{{ $item->product->formattedPrice() }}</td>
                        <td>
                            <form method="POST" action="{{ route('keranjang.update', $item) }}" style="display:inline">
                                @csrf
                                @method('PUT')
                                <label for="jumlah-{{ $item->id }}">Jumlah</label>
                                <input class="input" id="jumlah-{{ $item->id }}" type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="99">
                                <button type="submit">Ubah</button>
                            </form>
                        </td>
                        <td>{{ 'Rp '.number_format($item->subtotalIdr(), 0, ',', '.') }}</td>
                        <td>
                            <form method="POST" action="{{ route('keranjang.destroy', $item) }}" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p><strong>Total: {{ 'Rp '.number_format($total_idr, 0, ',', '.') }}</strong></p>
            @if ($blocked->isNotEmpty())
                <p>Checkout diblokir: perbaiki jumlah atau hapus item yang bermasalah di atas.</p>
            @else
                <p><a href="{{ route('checkout.show') }}">Lanjut ke Checkout</a></p>
            @endif
            <p><a href="{{ route('katalog') }}">Lanjut belanja</a></p>
        @endif
    </div>
@endsection
