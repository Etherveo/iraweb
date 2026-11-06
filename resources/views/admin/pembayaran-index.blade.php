@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4b (UI.md §6.6, §9.4, §10.1). Semua filter GET
         (status, metode, dari, sampai), label SIMULASI, tabel baca-saja,
         pagination, dan teks asersi tetap sama. --}}
    @php
        $paymentChips = [
            'pending' => 'status-chip-warning',
            'paid' => 'status-chip-success',
            'failed' => 'status-chip-danger',
            'expired' => 'status-chip-muted',
            'cancelled' => 'status-chip-muted',
        ];
    @endphp
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Transaksi &bull; Pembayaran</p>
                <h1 class="admin-title">Daftar Pembayaran — SIMULASI</h1>
                <p class="admin-subtitle">Seluruh rekaman pembayaran di daftar ini bersifat simulasi dan hanya-baca.</p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-secondary" href="{{ route('admin.pesanan.index') }}">Kembali ke daftar pesanan</a>
            </div>
        </header>

        <div class="admin-sim-banner">
            <strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Daftar ini hanya-baca: admin tidak dapat mengubah status pembayaran.
        </div>

        <div class="card admin-panel">
            <form method="GET" action="{{ route('admin.pembayaran.index') }}" class="admin-filters">
                <div class="admin-filter-group">
                    <label for="status" class="admin-label">Status</label>
                    <select class="input" name="status" id="status">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\Payment::STATUS_LABELS as $code => $label)
                            <option value="{{ $code }}" @selected(request('status') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-group">
                    <label for="metode" class="admin-label">Metode</label>
                    <select class="input" name="metode" id="metode">
                        <option value="">Semua metode</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method['code'] }}" @selected(request('metode') === $method['code'])>{{ $method['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-group">
                    <label for="dari" class="admin-label">Dari tanggal</label>
                    <input class="input" type="date" name="dari" id="dari" value="{{ request('dari') }}">
                </div>
                <div class="admin-filter-group">
                    <label for="sampai" class="admin-label">Sampai tanggal</label>
                    <input class="input" type="date" name="sampai" id="sampai" value="{{ request('sampai') }}">
                </div>
                <div class="admin-filter-actions">
                    <button class="btn-primary" type="submit">Filter</button>
                    @if (request()->filled('status') || request()->filled('metode') || request()->filled('dari') || request()->filled('sampai'))
                        <a class="btn-secondary" href="{{ route('admin.pembayaran.index') }}">Reset</a>
                    @endif
                </div>
            </form>

            @if ($payments->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada pembayaran.</p>
                    <p class="admin-empty-text">Pembayaran simulasi yang dibuat untuk pesanan akan tampil di sini.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Pesanan</th>
                                <th>Pembeli</th>
                                <th>Metode</th>
                                <th>Nominal</th>
                                <th>Status</th>
                                <th>Waktu</th>
                                <th>Asal data</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td data-label="Pesanan">
                                    <a class="admin-cell-link" href="{{ route('admin.pesanan.show', $payment->order) }}">{{ $payment->order->order_number }}</a>
                                </td>
                                <td data-label="Pembeli">
                                    <span class="admin-cell-primary">{{ $payment->user->name }}</span>
                                </td>
                                <td data-label="Metode">{{ config('payment.methods.'.$payment->payment_method.'.label', $payment->payment_method) }}</td>
                                <td data-label="Nominal">
                                    <span class="admin-cell-price">{{ $payment->formattedAmount() }}</span>
                                </td>
                                <td data-label="Status">
                                    <span class="status-chip {{ $paymentChips[$payment->status] ?? 'status-chip-muted' }}">
                                        <span>{{ $payment->statusLabel() }}</span>
                                    </span>
                                </td>
                                <td data-label="Waktu">
                                    <span class="admin-cell-nowrap">{{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</span>
                                </td>
                                <td data-label="Asal data">
                                    @if ($payment->origin === 'developer_simulation')
                                        <span class="badge-simulasi">SIMULASI</span>
                                    @else
                                        <span>Normal</span>
                                    @endif
                                </td>
                                <td data-label="Aksi">
                                    <a class="admin-action-link" href="{{ route('admin.pembayaran.show', $payment) }}">Detail &amp; jejak</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $payments->links() }}</div>
            @endif
        </div>
    </div>
@endsection
