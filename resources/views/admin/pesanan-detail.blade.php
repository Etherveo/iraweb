@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Pesanan {{ $order->order_number }}</h1>
        <p>Status: <strong>{{ $order->statusLabel() }}</strong></p>
        <p>Pembeli: {{ $order->user?->name }} ({{ $order->user?->email }})</p>
        <p>Asal data: {{ $order->origin === 'developer_simulation' ? 'SIMULASI' : 'Normal' }} @if ($order->simulation_run_id) — run: {{ $order->simulation_run_id }} @endif</p>
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
                <p>Metode: {{ config('payment.methods.'.$payment->payment_method.'.label', $payment->payment_method) }} — Status: {{ $payment->statusLabel() }} — Nominal: {{ $payment->formattedAmount() }} — Referensi: {{ $payment->reference_code }} — <a href="{{ route('admin.pembayaran.show', $payment) }}">Detail &amp; jejak</a></p>
                @if ($payment->transitions->isNotEmpty())
                    <ul>
                    @foreach ($payment->transitions->sortBy('occurred_at') as $transition)
                        <li>{{ $transition->from_status ?? '—' }} → {{ $transition->to_status }} ({{ $transition->trigger }}) — {{ $transition->occurred_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</li>
                    @endforeach
                    </ul>
                @endif
            @endforeach
        @endif
        <h2>Riwayat Status</h2>
        @if ($order->histories->isEmpty())
            <p>Belum ada riwayat status.</p>
        @else
            <ul>
            @foreach ($order->histories->sortByDesc('occurred_at') as $history)
                <li>{{ $history->from_status ?? '—' }} → {{ $history->to_status }} (pemicu: {{ $history->trigger }}@if ($history->actor_type), pelaku: {{ $history->actor_type }} #{{ $history->actor_id }}@endif) — {{ $history->occurred_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA @if ($history->reason) — alasan: {{ $history->reason }} @endif</li>
            @endforeach
            </ul>
        @endif
        <h2>Pemenuhan</h2>
        @if ($order->status === 'dibayar')
            <form method="POST" action="{{ route('admin.pesanan.status', $order) }}">
                @csrf
                <input type="hidden" name="to_status" value="diproses">
                <button class="btn-primary" type="submit">Tandai Diproses</button>
            </form>
        @elseif ($order->status === 'diproses')
            <form method="POST" action="{{ route('admin.pesanan.status', $order) }}">
                @csrf
                <input type="hidden" name="to_status" value="selesai">
                <button class="btn-primary" type="submit">Tandai Selesai</button>
            </form>
        @else
            <p>Tidak ada aksi pemenuhan untuk status {{ $order->statusLabel() }}.</p>
        @endif
        <p>Admin tidak dapat membatalkan pesanan; pembatalan hanya oleh pembeli atau karena kedaluwarsa.</p>
        <p><a href="{{ route('admin.pesanan.index') }}">Kembali ke daftar pesanan</a></p>
    </div>
@endsection
