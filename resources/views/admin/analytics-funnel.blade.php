{{-- M5 FR-03 Funnel (I3): dilihat → keranjang → booking → pembelian
(pembelian = dibayar, keputusan 2026-11-08). Angka memakai facts yang
sama dengan FR-01/FR-02, jadi konsisten dengan dashboard dan tren. --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <div class="admin-page-head">
        <div>
            <p class="admin-eyebrow">Analitik · Funnel</p>
            <h1>Funnel Pembelian</h1>
            <p class="admin-page-sub">Alur dilihat → keranjang → booking → pembelian (dibayar), {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} (WITA).</p>
        </div>
    </div>

    @include('admin._analytics-filter', ['filterRoute' => 'admin.funnel'])

    @if ($unavailable)
        @include('admin._analytics-unavailable', ['config_error' => $config_error ?? false])
    @else
        @if ($has_events && $chart)
            <div class="card">
                <h2>Grafik funnel</h2>
                <div class="chart-wrap"><canvas data-chart data-chart-config='@json($chart)' aria-label="Grafik funnel pembelian" role="img"></canvas></div>
            </div>
        @endif

        <div class="card">
            <h2>Tahap funnel</h2>
            @if ($has_events)
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Tahap</th><th>Jumlah</th><th>vs tahap sebelumnya</th><th>vs dilihat</th></tr>
                        </thead>
                        <tbody>
                            @foreach (($funnel['stages'] ?? []) as $stage)
                                <tr>
                                    <td>{{ $stage['label'] }}@if ($stage['anomaly'] ?? false) <span class="analytics-note">(catatan data)</span>@endif</td>
                                    <td>{{ number_format((int) $stage['count']) }}</td>
                                    <td>{{ ($stage['ratio_prev'] ?? null) !== null ? number_format((float) $stage['ratio_prev'] * 100, 1, ',', '.').'%' : ($stage['ratio_prev_text'] ?? '—') }}</td>
                                    <td>{{ ($stage['ratio_view'] ?? null) !== null ? number_format((float) $stage['ratio_view'] * 100, 1, ',', '.').'%' : ($stage['ratio_view_text'] ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="analytics-note">Belum ada aktivitas funnel pada periode ini.</p>
            @endif
            @if (! empty($funnel['largest_drop']))
                @php $drop = $funnel['largest_drop']; @endphp
                <p class="analytics-note">Penurunan terbesar: {{ $drop['from_label'] }} → {{ $drop['to_label'] }} ({{ number_format((int) $drop['diff']) }} event, {{ number_format((float) $drop['ratio'] * 100, 1, ',', '.') }}% dari {{ $drop['from_label'] }}).</p>
            @endif
            @if (! empty($funnel['anomalies']))
                <ul class="analytics-note-list">
                    @foreach ($funnel['anomalies'] as $anomaly)
                        <li>{{ $anomaly['note'] }} ({{ number_format((int) $anomaly['from_count']) }} → {{ number_format((int) $anomaly['to_count']) }}).</li>
                    @endforeach
                </ul>
            @endif
            <p class="analytics-note">Booking yang belum dibeli: {{ number_format((int) ($funnel['bookings_not_purchased'] ?? 0)) }}. Pembatalan: {{ number_format((int) ($funnel['cancels'] ?? 0)) }}.</p>
        </div>

        <div class="card">
            <h2>Funnel per produk</h2>
            @if (! empty($funnel['per_product']))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Produk</th><th>Dilihat</th><th>Keranjang</th><th>Booking</th><th>Pembelian</th><th>Konversi</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($funnel['per_product'] as $product)
                                <tr>
                                    <td>{{ $product['name'] }}</td>
                                    <td>{{ number_format((int) $product['views']) }}</td>
                                    <td>{{ number_format((int) $product['add_to_carts']) }}</td>
                                    <td>{{ number_format((int) $product['bookings']) }}</td>
                                    <td>{{ number_format((int) $product['purchases']) }}</td>
                                    <td>{{ ($product['conversion_to_view'] ?? null) !== null ? number_format((float) $product['conversion_to_view'] * 100, 1, ',', '.').'%' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="analytics-note">Tidak ada aktivitas funnel pada periode ini.</p>
            @endif
        </div>

        @if (! empty($funnel['warnings']))
            <div class="card analytics-warnings">
                <h2>Catatan data</h2>
                <ul>
                    @foreach ($funnel['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection
