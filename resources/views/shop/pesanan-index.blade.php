@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Pesanan Saya</h1>
        <form method="GET" action="{{ route('pesanan.index') }}">
            <select name="status">
                <option value="">Semua status</option>
                @foreach (\App\Models\Order::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn-primary" type="submit">Filter</button>
        </form>
        @if ($orders->isEmpty())
            <p>Belum ada pesanan.</p>
            <p><a href="{{ route('katalog') }}">Lihat katalog</a> untuk mulai berbelanja.</p>
        @else
            <table>
                <thead><tr><th>Nomor</th><th>Tanggal (WITA)</th><th>Total</th><th>Status</th><th>Asal data</th></tr></thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td><a href="{{ route('pesanan.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                        <td>{{ $order->formattedTotal() }}</td>
                        <td>{{ $order->statusLabel() }}</td>
                        <td>
                            @if ($order->origin === 'developer_simulation')
                                <span class="badge-simulasi">SIMULASI</span>
                            @else
                                Normal
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $orders->links() }}
        @endif
    </div>
@endsection
