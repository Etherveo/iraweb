{{-- M5 FR-01 Dashboard (ANL-01..12, K7/K8/K9): KPI + grafik penjualan +
Terlaris top 5 (ANL-12). Tanpa data → keadaan kosong yang jujur, bukan
nol yang menyesatkan. --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <header class="admin-head">
        <div>
            <p class="admin-eyebrow">Analitik &bull; Dashboard</p>
            <h1 class="admin-title">Dashboard Analitik</h1>
            <p class="admin-subtitle">Ringkasan penjualan {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} (WITA).</p>
        </div>
    </header>

    @include('admin._analytics-filter', ['filterRoute' => 'admin.dashboard'])

    @if ($unavailable)
        @include('admin._analytics-unavailable', ['config_error' => $config_error ?? false])
    @elseif (($summary['empty'] ?? false) === true)
        <div class="card admin-panel analytics-empty">
            <div class="analytics-state-icon is-muted" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M3 3V19C3 20.1046 3.89543 21 5 21H21M7 14L11 10L15 14L20 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="analytics-state-body">
                <h2 class="admin-section-title">Belum ada data pada periode ini</h2>
                <p class="analytics-note">Tidak ada penjualan berbayar antara {{ $dari }} dan {{ $sampai }}. Ubah periode di atas, atau isi data lewat Sistem Asli dan panel Developer.</p>
            </div>
        </div>
    @else
        @php
            $kpis = $summary['kpis'] ?? [];
            $topSelling = $summary['top_selling'] ?? [];
            $leastSelling = $summary['least_selling'] ?? [];
            $maxTopUnits = ! empty($topSelling) ? max(1, max(array_map(fn ($i) => (int) ($i['units_sold'] ?? 0), $topSelling))) : 1;
            $maxLeastUnits = ! empty($leastSelling) ? max(1, max(array_map(fn ($i) => (int) ($i['units_sold'] ?? 0), $leastSelling))) : 1;
        @endphp
        <div class="analytics-kpi-grid">
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Penjualan</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4.5" width="15" height="11" rx="2.5" stroke="currentColor" stroke-width="1.6"/><circle cx="10" cy="10" r="2.25" stroke="currentColor" stroke-width="1.6"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ ($kpis['revenue_idr']['value'] ?? null) !== null ? 'Rp '.number_format((int) $kpis['revenue_idr']['value'], 0, ',', '.') : ($kpis['revenue_idr']['text'] ?? 'Belum dapat dihitung') }}</span>
                @include('admin._kpi-change', ['vs' => $kpis['revenue_idr']['vs_previous'] ?? null])
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Transaksi</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 3.5H15C15.8284 3.5 16.5 4.17157 16.5 5V16.5L14 15L11.5 16.5L10 15L8.5 16.5L6 15L3.5 16.5V5C3.5 4.17157 4.17157 3.5 5 3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M7 8H13M7 11H11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ ($kpis['transactions']['value'] ?? null) !== null ? number_format((int) $kpis['transactions']['value']) : ($kpis['transactions']['text'] ?? 'Belum dapat dihitung') }}</span>
                @include('admin._kpi-change', ['vs' => $kpis['transactions']['vs_previous'] ?? null])
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Unit terjual</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5L16.5 6.25V13.75L10 17.5L3.5 13.75V6.25L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3.5 6.25L10 10L16.5 6.25M10 10V17.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ ($kpis['units_sold']['value'] ?? null) !== null ? number_format((int) $kpis['units_sold']['value']) : ($kpis['units_sold']['text'] ?? 'Belum dapat dihitung') }}</span>
                @include('admin._kpi-change', ['vs' => $kpis['units_sold']['vs_previous'] ?? null])
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Pembelian</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 6.5H15L14.2 15.5C14.1 16.3 13.4 17 12.6 17H7.4C6.6 17 5.9 16.3 5.8 15.5L5 6.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M7.5 6.5V5C7.5 3.61929 8.61929 2.5 10 2.5C11.3807 2.5 12.5 3.61929 12.5 5V6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ ($kpis['purchases']['value'] ?? null) !== null ? number_format((int) $kpis['purchases']['value']) : ($kpis['purchases']['text'] ?? 'Belum dapat dihitung') }}</span>
                @include('admin._kpi-change', ['vs' => $kpis['purchases']['vs_previous'] ?? null])
            </div>
        </div>

        @if (collect($kpis)->contains(fn ($k) => ($k['vs_previous']['descriptive_only'] ?? false) === true) || ($summary['transactions_differ_from_purchases'] ?? false))
            <div class="analytics-footnotes">
                @if (collect($kpis)->contains(fn ($k) => ($k['vs_previous']['descriptive_only'] ?? false) === true))
                    <p class="analytics-note">* Perbandingan penjualan (Rp) bersifat deskriptif, bukan klaim tren.</p>
                @endif
                @if ($summary['transactions_differ_from_purchases'] ?? false)
                    <p class="analytics-note">Jumlah transaksi ({{ number_format((int) ($kpis['transactions']['value'] ?? 0)) }} pesanan dibayar) berbeda dari pembelian ({{ number_format((int) ($kpis['purchases']['value'] ?? 0)) }} item dibeli) karena satu pesanan dapat berisi beberapa item.</p>
                @endif
            </div>
        @endif

        <div class="card admin-panel">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title">Penjualan per hari</h2>
                    <p class="analytics-card-sub">{{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} &bull; Pembayaran berhasil</p>
                </div>
                <span class="analytics-legend-pill">
                    <span class="analytics-legend-dot" aria-hidden="true"></span>
                    Penjualan (Rp)
                </span>
            </div>
            @if ($chart)
                <div class="analytics-chart-surface">
                    <div class="chart-wrap"><canvas data-chart data-chart-rupiah data-chart-config='@json($chart)' aria-label="Grafik penjualan per hari" role="img"></canvas></div>
                </div>
            @endif
            @if (! empty($summary['peak_day']))
                <div class="analytics-insight-bar">
                    <svg class="analytics-insight-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 2.5C6.96243 2.5 4.5 4.96243 4.5 8C4.5 9.9615 5.5276 11.6828 7.0728 12.6562C7.4981 12.9242 7.75 13.3976 7.75 13.9V14.5C7.75 15.0523 8.19772 15.5 8.75 15.5H11.25C11.8023 15.5 12.25 15.0523 12.25 14.5V13.9C12.25 13.3976 12.5019 12.9242 12.9272 12.6562C14.4724 11.6828 15.5 9.9615 15.5 8C15.5 4.96243 13.0376 2.5 10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M8.25 17.5H11.75" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <p class="analytics-note">Hari penjualan tertinggi: <strong>{{ \Illuminate\Support\Carbon::parse($summary['peak_day']['date'])->format('d M Y') }}</strong>@if (isset($summary['peak_day']['revenue_idr'])) (Rp {{ number_format((int) $summary['peak_day']['revenue_idr'], 0, ',', '.') }})@endif.</p>
                </div>
            @endif
        </div>

        <div class="analytics-split-grid">
            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Terlaris</h2>
                        <p class="analytics-card-sub">Urutan jumlah unit terjual terbanyak &bull; pembayaran berhasil</p>
                    </div>
                </div>
                @if (! empty($topSelling))
                    <ol class="analytics-list analytics-rank-list">
                        @foreach ($topSelling as $item)
                            @php
                                $pct = round(((int) ($item['units_sold'] ?? 0) / $maxTopUnits) * 100);
                            @endphp
                            <li class="analytics-rank-item">
                                <div class="analytics-rank-row">
                                    <span class="analytics-rank-name">{{ $item['name'] }}</span>
                                    <span class="analytics-rank-meta">{{ number_format((int) $item['units_sold']) }} unit terjual · Rp {{ number_format((int) $item['revenue_idr'], 0, ',', '.') }}</span>
                                </div>
                                <div class="analytics-bar-track" aria-hidden="true">
                                    <span class="analytics-bar-fill" style="width: {{ max(6, $pct) }}%"></span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="analytics-note">Belum ada produk yang memenuhi ambang terlaris (minimal 1 unit dibayar) pada periode ini.</p>
                @endif
            </div>

            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Paling sedikit terjual</h2>
                        <p class="analytics-card-sub">Urutan unit terjual paling rendah pada periode pilihan</p>
                    </div>
                </div>
                @if (! empty($leastSelling))
                    <ol class="analytics-list analytics-rank-list">
                        @foreach ($leastSelling as $item)
                            @php
                                $pct = round(((int) ($item['units_sold'] ?? 0) / $maxLeastUnits) * 100);
                            @endphp
                            <li class="analytics-rank-item">
                                <div class="analytics-rank-row">
                                    <span class="analytics-rank-name">{{ $item['name'] }}</span>
                                    <span class="analytics-rank-meta">{{ number_format((int) $item['units_sold']) }} unit terjual · Rp {{ number_format((int) $item['revenue_idr'], 0, ',', '.') }}</span>
                                </div>
                                <div class="analytics-bar-track" aria-hidden="true">
                                    <span class="analytics-bar-fill is-muted" style="width: {{ max(6, $pct) }}%"></span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="analytics-note">Tidak ada produk yang terjual pada periode ini.</p>
                @endif
            </div>
        </div>

        @if (! empty($summary['warnings']))
            <div class="card admin-panel analytics-warnings">
                <h2 class="admin-section-title">Catatan data</h2>
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
