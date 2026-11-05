@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Riwayat Stok: {{ $product->name }}</h1>
        <p>Riwayat ini baca-saja dan tidak dapat diubah — setiap perubahan stok menambah satu baris baru.</p>
        @if ($movements->isEmpty())
            <p>Belum ada riwayat stok untuk produk ini.</p>
        @else
            <table>
                <thead><tr><th>Waktu (WITA)</th><th>Jenis</th><th>Perubahan fisik</th><th>Perubahan dicadangkan</th><th>Referensi pesanan</th><th>Pelaku</th><th>Asal data</th><th>Catatan</th></tr></thead>
                <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td>{{ $movement->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                        <td>{{ $movement->type }}</td>
                        <td>{{ $movement->delta_on_hand }}</td>
                        <td>{{ $movement->delta_reserved }}</td>
                        <td>{{ $movement->order_id ?? '-' }}</td>
                        <td>{{ $movement->actor_type }}{{ $movement->actor_id ? ' #'.$movement->actor_id : '' }}</td>
                        <td>{{ $movement->origin }}</td>
                        <td>{{ $movement->note }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $movements->links() }}
        @endif
        <p><a href="{{ route('admin.produk.index') }}">Kembali ke daftar produk</a></p>
    </div>
@endsection
