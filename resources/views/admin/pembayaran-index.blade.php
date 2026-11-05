@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Daftar Pembayaran — SIMULASI</h1>
        <p><strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Daftar ini hanya-baca: admin tidak dapat mengubah status pembayaran.</p>
        <form method="GET" action="{{ route('admin.pembayaran.index') }}">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">Semua status</option>
                @foreach (\App\Models\Payment::STATUS_LABELS as $code => $label)
                    <option value="{{ $code }}" @selected(request('status') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <label for="metode">Metode</label>
            <select name="metode" id="metode">
                <option value="">Semua metode</option>
                @foreach ($methods as $method)
                    <option value="{{ $method['code'] }}" @selected(request('metode') === $method['code'])>{{ $method['label'] }}</option>
                @endforeach
            </select>
            <label for="dari">Dari tanggal</label>
            <input type="date" name="dari" id="dari" value="{{ request('dari') }}">
            <label for="sampai">Sampai tanggal</label>
            <input type="date" name="sampai" id="sampai" value="{{ request('sampai') }}">
            <button type="submit">Filter</button>
        </form>
        @if ($payments->isEmpty())
            <p>Belum ada pembayaran.</p>
        @else
            <table>
                <thead><tr><th>Pesanan</th><th>Pembeli</th><th>Metode</th><th>Nominal</th><th>Status</th><th>Waktu</th><th>Asal data</th><th></th></tr></thead>
                <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $payment->order->order_number }}</td>
                        <td>{{ $payment->user->name }}</td>
                        <td>{{ config('payment.methods.'.$payment->payment_method.'.label', $payment->payment_method) }}</td>
                        <td>{{ $payment->formattedAmount() }}</td>
                        <td>{{ $payment->statusLabel() }}</td>
                        <td>{{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</td>
                        <td>{{ $payment->origin === 'developer_simulation' ? 'SIMULASI' : 'Normal' }}</td>
                        <td><a href="{{ route('admin.pembayaran.show', $payment) }}">Detail &amp; jejak</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $payments->links() }}
        @endif
        <p><a href="{{ route('admin.pesanan.index') }}">Kembali ke daftar pesanan</a></p>
    </div>
@endsection
