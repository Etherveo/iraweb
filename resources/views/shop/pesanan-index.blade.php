@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 6 (UI.md §2.4, §6, §9.2, §10.1, §16, referensi Figma 3:104501).
         Filter status, tabel riwayat pesanan, lencana SIMULASI, dan pagination dipertahankan. --}}
    @php
        $statusChips = [
            'menunggu_pembayaran' => 'status-chip-warning',
            'dibayar' => 'status-chip-success',
            'diproses' => 'status-chip-info',
            'dikirim' => 'status-chip-info',
            'selesai' => 'status-chip-success',
            'dibatalkan' => 'status-chip-danger',
            'kedaluwarsa' => 'status-chip-muted',
        ];
    @endphp
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Pesanan</p>
                <h1 class="admin-title">Pesanan Saya</h1>
                <p class="admin-subtitle">Pantau status dan buka rincian setiap pesananmu.</p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-secondary" href="{{ route('katalog') }}">Lanjut belanja</a>
            </div>
        </header>

        <div class="card admin-panel">
            <form method="GET" action="{{ route('pesanan.index') }}" class="admin-filters">
                <div class="admin-filter-group">
                    <label class="admin-label" for="filter-status-pesanan">Status pesanan</label>
                    <select class="input" id="filter-status-pesanan" name="status">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\Order::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button class="btn-primary" type="submit">Filter</button>
                    @if (request()->filled('status'))
                        <a class="btn-secondary" href="{{ route('pesanan.index') }}">Reset</a>
                    @endif
                </div>
            </form>

            @if ($orders->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada pesanan.</p>
                    <p><a class="admin-cell-link" href="{{ route('katalog') }}">Lihat katalog</a> untuk mulai berbelanja.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Tanggal (WITA)</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Asal data</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td data-label="Nomor">
                                    <a class="admin-cell-link" href="{{ route('pesanan.show', $order) }}">{{ $order->order_number }}</a>
                                </td>
                                <td data-label="Tanggal (WITA)" class="admin-cell-nowrap">
                                    {{ $order->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA
                                </td>
                                <td data-label="Total" class="admin-cell-price">{{ $order->formattedTotal() }}</td>
                                <td data-label="Status">
                                    <span class="status-chip {{ $statusChips[$order->status] ?? 'status-chip-muted' }}">
                                        <span>{{ $order->statusLabel() }}</span>
                                    </span>
                                </td>
                                <td data-label="Asal data">
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
                </div>

                <div class="admin-pagination">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
