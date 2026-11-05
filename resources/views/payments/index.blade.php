@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Riwayat Pembayaran — SIMULASI</h1>
        <p><strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah; semua baris di bawah ini adalah pembayaran simulasi.</p>
        <form method="GET" action="{{ route('pembayaran.index') }}">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">Semua status</option>
                @foreach (\App\Models\Payment::STATUS_LABELS as $code => $label)
                    <option value="{{ $code }}" @selected(request('status') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
        </form>
        @if ($payments->isEmpty())
            <p>Belum ada pembayaran.</p>
        @else
            <table>
                <thead><tr><th>Pesanan</th><th>Metode</th><th>Nominal</th><th>Status</th><th>Waktu</th><th></th></tr></thead>
                <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $payment->order->order_number }}</td>
                        <td>{{ config('payment.methods.'.$payment->payment_method.'.label', $payment->payment_method) }}</td>
                        <td>{{ $payment->formattedAmount() }}</td>
                        <td>{{ $payment->statusLabel() }}</td>
                        <td>{{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</td>
                        <td><a href="{{ route('pembayaran.show', $payment) }}">Detail &amp; jejak</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $payments->links() }}
        @endif
        <p><a href="{{ route('pesanan.index') }}">Kembali ke daftar pesanan</a></p>
    </div>
@endsection
