@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Pesanan</h1>
        <form method="GET" action="{{ route('admin.pesanan.index') }}">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Cari nomor pesanan">
            <select name="status">
                <option value="">Semua status</option>
                @foreach (\App\Models\Order::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="asal">
                <option value="">Semua asal data</option>
                <option value="normal" @selected(request('asal') === 'normal')>Normal</option>
                <option value="developer_simulation" @selected(request('asal') === 'developer_simulation')>Simulasi</option>
            </select>
            <label for="dari">Dari tanggal</label>
            <input type="date" id="dari" name="dari" value="{{ request('dari') }}">
            <label for="sampai">Sampai tanggal</label>
            <input type="date" id="sampai" name="sampai" value="{{ request('sampai') }}">
            <button class="btn-primary" type="submit">Cari</button>
        </form>
        @if ($orders->isEmpty())
            <p>Belum ada pesanan.</p>
        @else
            <table>
                <thead><tr><th>Nomor</th><th>Pembeli</th><th>Tanggal (WITA)</th><th>Total</th><th>Status</th><th>Asal data</th></tr></thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.pesanan.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->user?->name }}<br><small>{{ $order->user?->email }}</small></td>
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
