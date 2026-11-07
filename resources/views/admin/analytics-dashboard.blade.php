{{-- M5 FR-01 Dashboard (ANL-01..12, K7/K8/K9): KPI + grafik penjualan +
Terlaris top 5 (ANL-12). Tanpa data → keadaan kosong yang jujur, bukan
nol yang menyesatkan. --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <div class="admin-page-head">
        <div>
            <p class="admin-eyebrow">Analitik · Dashboard</p>
            <h1>Dashboard Analitik</h1>
            <p class="admin-page-sub">Ringkasan penjualan {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} (WITA).</p>
        </div>
    </div>

    @include('admin._analytics-filter', ['filterRoute' => 'admin.dashboard'])

    @if ($unavailable)
        @include('admin._analytics-unavailable', ['config_error' => $config_error ?? false])
    @elseif (($summary['empty'] ?? false) === true)
        <div class="card analytics-empty">
            <h2>Belum ada data pada periode ini</h2>
            <p>Tidak ada penjualan berbayar antara {{ $dari }} dan {{ $sampai }}. Ubah periode di atas, atau isi data lewat Sistem Asli dan panel Developer.</p>
        </div>
    @else
        @php
            $kpis = $summary['kpis'] ?? [];
        @endphp
        <div class="analytics-kpi-grid">
            <div class="card analytics-kpi">
                <span class="analytics-kpi-label">Penjualan</span>
                <span class="analytics-kpi-value">{{ ($kpis['revenue_idr']['value'] ?? null) !== null ? 'Rp '.number_format((int) $kpis['revenue_idr']['value'], 0, ',', '.') : ($kpis['revenue_idr']['text'] ?? 'Belum dapat dihitung') }}</span>
                @include('admin._kpi-change', ['vs' => $kpis['revenue_idr']['vs_previous'] ?? null])
            </div>
            <div class="card analytics-kpi">
                <span class="analytics-kpi-label">Transaksi</span>
                <span class="analytics-kpi-value">{{ ($kpis['transactions']['value'] ?? null) !== null ? number_format((int) $kpis['transactions']['value']) : ($kpis['transactions']['text'] ?? 'Belum dapat dihitung') }}</span>
                @include('admin._kpi-change', ['vs' => $kpis['transactions']['vs_previous'] ?? null])
            </div>
            <div class="card analytics-kpi">
                <span class="analytics-kpi-label">Unit terjual</span>
                <span class="analytics-kpi-value">{{ ($kpis['units_sold']['value'] ?? null) !== null ? number_format((int) $kpis['units_sold']['value']) : ($kpis['units_sold']['text'] ?? 'Belum dapat dihitung') }}</span>
                @include('admin._kpi-change', ['vs' => $kpis['units_sold']['vs_previous'] ?? null])
            </div>
            <div class="card analytics-kpi">
                <span class="analytics-kpi-label">Pembelian</span>
                <span class="analytics-kpi-value">{{ ($kpis['purchases']['value'] ?? null) !== null ? number_format((int) $kpis['purchases']['value']) : ($kpis['purchases']['text'] ?? 'Belum dapat dihitung') }}</span>
                @include('admin._kpi-change', ['vs' => $kpis['purchases']['vs_previous'] ?? null])
            </div>
        </div>
        @if (collect($kpis)->contains(fn ($k) => ($k['vs_previous']['descriptive_only'] ?? false) === true))
            <p class="analytics-note">* Perbandingan penjualan (Rp) bersifat deskriptif, bukan klaim tren.</p>
        @endif
        @if ($summary['transactions_differ_from_purchases'] ?? false)
            <p class="analytics-note">Jumlah transaksi ({{ number_format((int) ($kpis['transactions']['value'] ?? 0)) }} pesanan dibayar) berbeda dari pembelian ({{ number_format((int) ($kpis['purchases']['value'] ?? 0)) }} item dibeli) karena satu pesanan dapat berisi beberapa item.</p>
        @endif

        <div class="card">
            <h2>Penjualan per hari</h2>
            @if ($chart)
                <div class="chart-wrap"><canvas data-chart data-chart-rupiah data-chart-config='@json($chart)' aria-label="Grafik penjualan per hari" role="img"></canvas></div>
            @endif
            @if (! empty($summary['peak_day']))
                <p class="analytics-note">Hari penjualan tertinggi: {{ \Illuminate\Support\Carbon::parse($summary['peak_day']['date'])->format('d M Y') }}.</p>
            @endif
        </div>

        <div class="card">
            <h2>Terlaris</h2>
            @if (! empty($summary['top_selling']))
                <ol class="analytics-list">
                    @foreach ($summary['top_selling'] as $item)
                        <li><span>{{ $item['name'] }}</span><span>{{ number_format((int) $item['units_sold']) }} unit terjual · Rp {{ number_format((int) $item['revenue_idr'], 0, ',', '.') }}</span></li>
                    @endforeach
                </ol>
            @else
                <p class="analytics-note">Belum ada produk yang memenuhi ambang terlaris (minimal 1 unit dibayar) pada periode ini.</p>
            @endif
        </div>

        <div class="card">
            <h2>Paling sedikit terjual</h2>
            @if (! empty($summary['least_selling']))
                <ol class="analytics-list">
                    @foreach ($summary['least_selling'] as $item)
                        <li><span>{{ $item['name'] }}</span><span>{{ number_format((int) $item['units_sold']) }} unit terjual · Rp {{ number_format((int) $item['revenue_idr'], 0, ',', '.') }}</span></li>
                    @endforeach
                </ol>
            @else
                <p class="analytics-note">Tidak ada produk yang terjual pada periode ini.</p>
            @endif
        </div>

        @if (! empty($summary['warnings']))
            <div class="card analytics-warnings">
                <h2>Catatan data</h2>
                <ul>
                    @foreach ($summary['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection
