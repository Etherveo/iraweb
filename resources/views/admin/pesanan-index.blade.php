@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4b (UI.md §6.6, §9.4). Semua filter GET (q, status,
         asal, dari, sampai), lencana SIMULASI, pagination, dan teks asersi tetap sama. --}}
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
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Transaksi &bull; Pesanan</p>
                <h1 class="admin-title">Pesanan</h1>
                <p class="admin-subtitle">Daftar pesanan masuk dari pembeli dan simulator, lengkap dengan filter status, asal data, dan rentang tanggal.</p>
            </div>
        </header>

        <div class="card admin-panel">
            <form method="GET" action="{{ route('admin.pesanan.index') }}" class="admin-filters">
                <div class="admin-filter-group admin-filter-grow">
                    <label for="filter-q" class="admin-label">Cari nomor pesanan</label>
                    <input class="input" id="filter-q" name="q" value="{{ request('q') }}" placeholder="Cari nomor pesanan">
                </div>
                <div class="admin-filter-group">
                    <label for="filter-status" class="admin-label">Status</label>
                    <select class="input" id="filter-status" name="status">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\Order::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-group">
                    <label for="filter-asal" class="admin-label">Asal data</label>
                    <select class="input" id="filter-asal" name="asal">
                        <option value="">Semua asal data</option>
                        <option value="normal" @selected(request('asal') === 'normal')>Normal</option>
                        <option value="developer_simulation" @selected(request('asal') === 'developer_simulation')>Simulasi</option>
                    </select>
                </div>
                <div class="admin-filter-group">
                    <label for="dari" class="admin-label">Dari tanggal</label>
                    <input class="input" type="date" id="dari" name="dari" value="{{ request('dari') }}">
                </div>
                <div class="admin-filter-group">
                    <label for="sampai" class="admin-label">Sampai tanggal</label>
                    <input class="input" type="date" id="sampai" name="sampai" value="{{ request('sampai') }}">
                </div>
                <div class="admin-filter-actions">
                    <button class="btn-primary" type="submit">Cari</button>
                    @if (request()->filled('q') || request()->filled('status') || request()->filled('asal') || request()->filled('dari') || request()->filled('sampai'))
                        <a class="btn-secondary" href="{{ route('admin.pesanan.index') }}">Reset</a>
                    @endif
                </div>
            </form>

            @if ($orders->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada pesanan.</p>
                    <p class="admin-empty-text">Pesanan yang dibuat pembeli atau simulator pada filter ini akan tampil di sini.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Pembeli</th>
                                <th>Tanggal (WITA)</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Asal data</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td data-label="Nomor">
                                    <a class="admin-cell-link" href="{{ route('admin.pesanan.show', $order) }}">{{ $order->order_number }}</a>
                                </td>
                                <td data-label="Pembeli">
                                    <div>
                                        <span class="admin-cell-primary">{{ $order->user?->name }}</span>
                                        <div class="admin-cell-sub">{{ $order->user?->email }}</div>
                                    </div>
                                </td>
                                <td data-label="Tanggal (WITA)">
                                    <span class="admin-cell-nowrap">{{ $order->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</span>
                                </td>
                                <td data-label="Total">
                                    <span class="admin-cell-price">{{ $order->formattedTotal() }}</span>
                                </td>
                                <td data-label="Status">
                                    <span class="status-chip {{ $statusChips[$order->status] ?? 'status-chip-muted' }}">
                                        <span>{{ $order->statusLabel() }}</span>
                                    </span>
                                </td>
                                <td data-label="Asal data">
                                    @if ($order->origin === 'developer_simulation')
                                        <span class="badge-simulasi">SIMULASI</span>
                                    @else
                                        <span>Normal</span>
                                    @endif
                                </td>
                                <td data-label="Aksi">
                                    <a class="admin-action-link" href="{{ route('admin.pesanan.show', $order) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
@endsection
