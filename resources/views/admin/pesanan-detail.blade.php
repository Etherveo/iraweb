@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4b (UI.md §6.1, §6.6, §9.4 + Figma "Detail pesanan").
         Semua informasi pesanan, item, pembayaran simulasi, linimasa riwayat
         status, form pemenuhan POST, dan teks asersi tetap sama. --}}
    @php
        $statusChips = [
            'menunggu_pembayaran' => 'status-chip-warning',
            'dibayar' => 'status-chip-success',
            'diproses' => 'status-chip-info',
            'selesai' => 'status-chip-success',
            'dibatalkan' => 'status-chip-danger',
            'kedaluwarsa' => 'status-chip-muted',
        ];
    @endphp
    <div class="admin-page">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('admin.pesanan.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke daftar pesanan</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Transaksi &bull; Pesanan</p>
                <h1 class="admin-title">Pesanan {{ $order->order_number }}</h1>
                <p class="admin-subtitle">Rincian item pesanan, linimasa status, pembayaran, dan pemenuhan pesanan.</p>
            </div>
            <div class="admin-head-actions">
                <span class="status-chip {{ $statusChips[$order->status] ?? 'status-chip-muted' }}">
                    <span>Status: <strong>{{ $order->statusLabel() }}</strong></span>
                </span>
            </div>
        </header>

        <div class="card admin-panel">
            <h2 class="admin-section-title">Ringkasan pesanan</h2>
            <div class="admin-kv-grid">
                <div class="admin-kv-item">
                    <span class="admin-kv-label">Pembeli</span>
                    <span class="admin-kv-value">{{ $order->user?->name }} ({{ $order->user?->email }})</span>
                </div>
                <div class="admin-kv-item">
                    <span class="admin-kv-label">Dibuat</span>
                    <span class="admin-kv-value">{{ $order->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</span>
                </div>
                <div class="admin-kv-item">
                    <span class="admin-kv-label">Batas waktu bayar</span>
                    <span class="admin-kv-value">{{ $order->payment_deadline_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</span>
                </div>
                @if ($order->paid_at)
                    <div class="admin-kv-item">
                        <span class="admin-kv-label">Dibayar</span>
                        <span class="admin-kv-value">{{ $order->paid_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</span>
                    </div>
                @endif
                @if ($order->pickup_at)
                    <div class="admin-kv-item">
                        <span class="admin-kv-label">Jadwal pengambilan</span>
                        <span class="admin-kv-value">{{ $order->pickup_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</span>
                    </div>
                @endif
                @if ($order->picked_up_at)
                    <div class="admin-kv-item">
                        <span class="admin-kv-label">Diambil</span>
                        <span class="admin-kv-value">{{ $order->picked_up_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="card admin-panel">
            <h2 class="admin-section-title">Item</h2>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga satuan</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td data-label="Produk">
                                <span class="admin-cell-primary">{{ $item->product_name_snapshot }}</span>
                            </td>
                            <td data-label="Harga satuan">{{ $item->formattedUnitPrice() }}</td>
                            <td data-label="Jumlah">{{ $item->quantity }}</td>
                            <td data-label="Subtotal">
                                <span class="admin-cell-price">{{ $item->formattedSubtotal() }}</span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="admin-order-total"><strong>Total: {{ $order->formattedTotal() }}</strong></p>
        </div>

        <div class="admin-detail-cols">
            <div class="card admin-panel">
                <h2 class="admin-section-title">Pembayaran</h2>
                @if ($order->payments->isEmpty())
                    <p class="admin-hint">Belum ada pembayaran.</p>
                @else
                    <div class="admin-stack">
                        @foreach ($order->payments as $payment)
                            <div class="admin-subcard">
                                <p class="admin-subcard-head">
                                    <span>Metode: <strong>{{ config('payment.methods.'.$payment->payment_method.'.label', $payment->payment_method) }}</strong></span>
                                    <span>&mdash; Status: <strong>{{ $payment->statusLabel() }}</strong></span>
                                    <span>&mdash; Nominal: <strong>{{ $payment->formattedAmount() }}</strong></span>
                                    <span>&mdash; Referensi: <code class="admin-code">{{ $payment->reference_code }}</code></span>
                                    <span>&mdash; <a class="admin-action-link" href="{{ route('admin.pembayaran.show', $payment) }}">Detail &amp; jejak</a></span>
                                </p>
                                @if ($payment->transitions->isNotEmpty())
                                    <ul class="admin-timeline">
                                    @foreach ($payment->transitions->sortBy('occurred_at') as $transition)
                                        <li>{{ $transition->from_status ?? '—' }} → {{ $transition->to_status }} ({{ $transition->trigger }}) — {{ $transition->occurred_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</li>
                                    @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card admin-panel">
                <h2 class="admin-section-title">Riwayat Status</h2>
                @if ($order->histories->isEmpty())
                    <p class="admin-hint">Belum ada riwayat status.</p>
                @else
                    <ul class="admin-timeline">
                    @foreach ($order->histories->sortByDesc('occurred_at') as $history)
                        <li>{{ $history->from_status ?? '—' }} → {{ $history->to_status }} (pemicu: {{ $history->trigger }}@if ($history->actor_type), pelaku: {{ $history->actor_type }} #{{ $history->actor_id }}@endif) — {{ $history->occurred_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA @if ($history->reason) — alasan: {{ $history->reason }} @endif</li>
                    @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="card admin-panel">
            <h2 class="admin-section-title">Pemenuhan</h2>
            @if ($order->status === 'dibayar')
                <form method="POST" action="{{ route('admin.pesanan.status', $order) }}" class="admin-form-actions">
                    @csrf
                    <input type="hidden" name="to_status" value="diproses">
                    <button class="btn-primary" type="submit">Tandai Siap Diambil</button>
                </form>
            @elseif ($order->status === 'diproses')
                <form method="POST" action="{{ route('admin.pesanan.status', $order) }}" class="admin-form-actions">
                    @csrf
                    <input type="hidden" name="to_status" value="selesai">
                    <button class="btn-primary" type="submit">Tandai Sudah Diambil</button>
                </form>
            @else
                <p class="admin-hint">Tidak ada aksi pemenuhan untuk status {{ $order->statusLabel() }}.</p>
            @endif
            <p class="admin-info-box">Admin tidak dapat membatalkan pesanan; pembatalan hanya oleh pembeli atau karena kedaluwarsa.</p>
            <div>
                <a class="btn-secondary" href="{{ route('admin.pesanan.index') }}">Kembali ke daftar pesanan</a>
            </div>
        </div>
    </div>
@endsection
