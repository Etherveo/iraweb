{{-- M5 FR-02 Tren (K1/K2/K3/K13): tren per produk atas metrik units.
Label dari modul ("Naik"/"Turun"/"Tidak ada tren jelas") — sesuai K3.
Periode <8 minggu penuh → "Data belum cukup" (K13). --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <header class="admin-head">
        <div>
            <p class="admin-eyebrow">Analitik &bull; Tren</p>
            <h1 class="admin-title">Tren Produk</h1>
            <p class="admin-subtitle">Arah penjualan per produk, {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} (WITA). Metrik: unit terjual per minggu.</p>
        </div>
    </header>

    @include('admin._analytics-filter', ['filterRoute' => 'admin.tren'])

    @if ($unavailable)
        @include('admin._analytics-unavailable', ['config_error' => $config_error ?? false])
    @else
        @php
            $products = $trend['products'] ?? [];
            $weeksFull = (int) ($trend['weeks_full'] ?? 0);
            $nMinWeeks = (int) ($trend['n_min_weeks'] ?? 8);
            $totalProducts = count($products);
            $countNaik = count(array_filter($products, fn ($p) => ($p['label_code'] ?? '') === 'naik'));
            $countTurun = count(array_filter($products, fn ($p) => ($p['label_code'] ?? '') === 'turun'));
            $countOther = max(0, $totalProducts - $countNaik - $countTurun);

            $maxMean = ! empty($products) ? max(1.0, max(array_map(fn ($p) => (float) ($p['mean'] ?? 0), $products))) : 1.0;

            $chartProducts = array_values(array_filter($products, fn ($p) => ($p['change_abs'] ?? null) !== null));
            $trendChart = null;
            if ($weeksFull >= $nMinWeeks && ! empty($chartProducts)) {
                $trendChart = [
                    'type' => 'bar',
                    'data' => [
                        'labels' => array_column($chartProducts, 'name'),
                        'datasets' => [[
                            'label' => 'Perubahan (unit)',
                            'data' => array_map(fn ($p) => round((float) ($p['change_abs'] ?? 0), 2), $chartProducts),
                            'backgroundColor' => array_map(function ($p) {
                                return match ($p['label_code'] ?? '') {
                                    'naik' => 'rgba(14, 159, 110, 0.85)',
                                    'turun' => 'rgba(214, 59, 90, 0.85)',
                                    default => 'rgba(102, 85, 245, 0.45)',
                                };
                            }, $chartProducts),
                            'borderRadius' => 8,
                        ]],
                    ],
                    'options' => [
                        'indexAxis' => 'y',
                        'responsive' => true,
                        'maintainAspectRatio' => false,
                        'plugins' => ['legend' => ['display' => false]],
                        'scales' => ['x' => ['beginAtZero' => true]],
                    ],
                ];
            }

            // Grafik garis mingguan (Figma "Arah penjualan produk"): unit
            // terjual per minggu untuk maks 5 produk — Naik/Turun dulu
            // (dari rankings), lalu total unit terbanyak. Deret tiap produk
            // disejajarkan pada gabungan minggu penuh; minggu sebelum
            // produk mulai (trimmed) bernilai null supaya garisnya
            // berhenti, bukan nol yang menyesatkan.
            $linePalette = ['#6655F5', '#8B7CF6', '#5B8DEF', '#7A9CC6', '#9B8AFB'];
            $rankedIds = array_merge(
                array_column($trend['rankings']['naik'] ?? [], 'product_id'),
                array_column($trend['rankings']['turun'] ?? [], 'product_id'),
            );
            $byProductId = [];
            foreach ($products as $p) {
                $byProductId[$p['product_id']] = $p;
            }
            $lineCandidates = [];
            foreach ($rankedIds as $pid) {
                if (isset($byProductId[$pid]) && ! empty($byProductId[$pid]['series'])) {
                    $lineCandidates[] = $byProductId[$pid];
                }
            }
            $rest = array_values(array_filter($products, fn ($p) => ! in_array($p['product_id'], $rankedIds, true) && ! empty($p['series'])));
            usort($rest, fn ($a, $b) => ((float) ($b['total_units'] ?? 0)) <=> ((float) ($a['total_units'] ?? 0)));
            $lineProducts = array_slice(array_merge($lineCandidates, $rest), 0, 5);

            $trendLineChart = null;
            if ($weeksFull >= $nMinWeeks && ! empty($lineProducts)) {
                $weekStarts = [];
                foreach ($lineProducts as $p) {
                    foreach ($p['series'] as $point) {
                        $weekStarts[$point['week_start']] = true;
                    }
                }
                $weekStarts = array_keys($weekStarts);
                sort($weekStarts);
                $lineDatasets = [];
                foreach ($lineProducts as $i => $p) {
                    $byWeek = [];
                    foreach ($p['series'] as $point) {
                        $byWeek[$point['week_start']] = (int) ($point['units'] ?? 0);
                    }
                    $color = match ($p['label_code'] ?? '') {
                        'naik' => '#0E9F6E',
                        'turun' => '#D63B5A',
                        default => $linePalette[$i % count($linePalette)],
                    };
                    $dataset = [
                        'label' => $p['name'],
                        'data' => array_map(fn ($ws) => $byWeek[$ws] ?? null, $weekStarts),
                        'borderColor' => $color,
                        'backgroundColor' => 'transparent',
                        'fill' => false,
                        'tension' => 0.3,
                        'pointRadius' => 2,
                    ];
                    if (($p['label_code'] ?? '') === 'turun') {
                        $dataset['borderDash'] = [6, 4];
                    }
                    $lineDatasets[] = $dataset;
                }
                $trendLineChart = [
                    'type' => 'line',
                    'data' => [
                        'labels' => array_map(fn ($ws) => \Illuminate\Support\Carbon::parse($ws)->format('d M'), $weekStarts),
                        'datasets' => $lineDatasets,
                    ],
                    'options' => [
                        'responsive' => true,
                        'maintainAspectRatio' => false,
                        'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
                        'scales' => [
                            'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Terjual (unit)']],
                        ],
                    ],
                ];
            }
        @endphp

        @if ($weeksFull < $nMinWeeks)
            <div class="admin-info-box analytics-alert-box" role="alert">
                <svg class="analytics-alert-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M10 6.5V10.5M10 13.5H10.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <p><strong>Data belum cukup.</strong> Periode ini hanya memuat {{ $weeksFull }} minggu penuh — tren membutuhkan minimal {{ $nMinWeeks }} minggu penuh. Perpanjang periode agar tren dapat dihitung.</p>
            </div>
        @endif

        <div class="analytics-kpi-grid">
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Minggu penuh</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><rect x="3" y="4.5" width="14" height="12.5" rx="2.5" stroke="currentColor" stroke-width="1.6"/><path d="M6.5 2.75V5.75M13.5 2.75V5.75M3 8.25H17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ $weeksFull }}</span>
                <span class="analytics-kpi-sub">Minimal {{ $nMinWeeks }} minggu untuk uji tren</span>
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Tren naik</span>
                    <span class="analytics-kpi-icon is-up" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M4 14L16 6M16 6H9M16 6V13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ $countNaik }}</span>
                <span class="analytics-kpi-sub">dari {{ $totalProducts }} produk dianalisis</span>
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Tren turun</span>
                    <span class="analytics-kpi-icon is-down" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M4 6L16 14M16 14H9M16 14V7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ $countTurun }}</span>
                <span class="analytics-kpi-sub">dari {{ $totalProducts }} produk dianalisis</span>
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Lainnya</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M4 10H16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ $countOther }}</span>
                <span class="analytics-kpi-sub">{{ $weeksFull < $nMinWeeks ? 'Data belum cukup' : 'Tidak ada tren jelas' }}</span>
            </div>
        </div>

        @if ($totalProducts > 0)
            <div class="card admin-panel analytics-distribution-card">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Sebaran arah tren ({{ $totalProducts }} produk)</h2>
                        <p class="analytics-card-sub">Proporsi hasil klasifikasi tren penjualan mingguan pada periode terpilih</p>
                    </div>
                    <div class="analytics-legend-group">
                        <span class="analytics-legend-pill"><span class="analytics-legend-dot is-up"></span>Naik ({{ $countNaik }})</span>
                        <span class="analytics-legend-pill"><span class="analytics-legend-dot is-down"></span>Turun ({{ $countTurun }})</span>
                        <span class="analytics-legend-pill"><span class="analytics-legend-dot is-neutral"></span>{{ $weeksFull < $nMinWeeks ? 'Data belum cukup' : 'Tidak ada tren jelas' }} ({{ $countOther }})</span>
                    </div>
                </div>
                <div class="analytics-stacked-bar" aria-hidden="true">
                    @if ($countNaik > 0)
                        <span class="analytics-stacked-seg is-up" style="width: {{ round(($countNaik / $totalProducts) * 100, 1) }}%"></span>
                    @endif
                    @if ($countTurun > 0)
                        <span class="analytics-stacked-seg is-down" style="width: {{ round(($countTurun / $totalProducts) * 100, 1) }}%"></span>
                    @endif
                    @if ($countOther > 0)
                        <span class="analytics-stacked-seg is-neutral" style="width: {{ round(($countOther / $totalProducts) * 100, 1) }}%"></span>
                    @endif
                </div>
            </div>
        @endif

        @if ($trendChart)
            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Visualisasi perubahan unit per produk</h2>
                        <p class="analytics-card-sub">Perbandingan estimasi perubahan unit terjual sepanjang {{ $weeksFull }} minggu penuh</p>
                    </div>
                </div>
                <div class="analytics-chart-surface">
                    <div class="chart-wrap"><canvas data-chart data-chart-config='@json($trendChart)' aria-label="Grafik perubahan unit per produk" role="img"></canvas></div>
                </div>
            </div>
        @endif

        @if ($trendLineChart)
            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Arah penjualan produk</h2>
                        <p class="analytics-card-sub">Unit terjual per minggu · {{ count($lineProducts) }} produk teratas (Naik/Turun dulu, lalu total unit terbanyak). Klik legenda untuk menampilkan/menyembunyikan garis; arahkan kursor untuk detail per minggu.</p>
                    </div>
                </div>
                <div class="analytics-chart-surface">
                    <div class="chart-wrap"><canvas data-chart data-chart-config='@json($trendLineChart)' aria-label="Grafik arah penjualan produk per minggu" role="img"></canvas></div>
                </div>
            </div>
        @endif

        @if (! empty($trend['rankings']['naik'] ?? []) || ! empty($trend['rankings']['turun'] ?? []))
            <div class="analytics-split-grid">
                @if (! empty($trend['rankings']['naik'] ?? []))
                    @php
                        $naikList = $trend['rankings']['naik'];
                        $maxNaikChange = max(1.0, max(array_map(fn ($p) => abs((float) ($p['change_abs'] ?? 0)), $naikList)));
                    @endphp
                    <div class="card admin-panel">
                        <div class="analytics-card-head">
                            <div>
                                <h2 class="admin-section-title">Naik</h2>
                                <p class="analytics-card-sub">Peringkat produk dengan tren kenaikan terkuat</p>
                            </div>
                        </div>
                        <ol class="analytics-list analytics-rank-list">
                            @foreach ($naikList as $product)
                                @php
                                    $pct = round((abs((float) ($product['change_abs'] ?? 0)) / $maxNaikChange) * 100);
                                @endphp
                                <li class="analytics-rank-item">
                                    <div class="analytics-rank-row">
                                        <span class="analytics-rank-name">{{ $product['name'] }}</span>
                                        <span class="analytics-rank-meta">r {{ number_format((float) ($product['r'] ?? 0), 2, ',', '.') }} · {{ number_format((float) ($product['change_abs'] ?? 0), 1, ',', '.') }} unit</span>
                                    </div>
                                    <div class="analytics-bar-track" aria-hidden="true">
                                        <span class="analytics-bar-fill is-up" style="width: {{ max(8, $pct) }}%"></span>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @if (! empty($trend['rankings']['turun'] ?? []))
                    @php
                        $turunList = $trend['rankings']['turun'];
                        $maxTurunChange = max(1.0, max(array_map(fn ($p) => abs((float) ($p['change_abs'] ?? 0)), $turunList)));
                    @endphp
                    <div class="card admin-panel">
                        <div class="analytics-card-head">
                            <div>
                                <h2 class="admin-section-title">Turun</h2>
                                <p class="analytics-card-sub">Peringkat produk dengan tren penurunan terdalam</p>
                            </div>
                        </div>
                        <ol class="analytics-list analytics-rank-list">
                            @foreach ($turunList as $product)
                                @php
                                    $pct = round((abs((float) ($product['change_abs'] ?? 0)) / $maxTurunChange) * 100);
                                @endphp
                                <li class="analytics-rank-item">
                                    <div class="analytics-rank-row">
                                        <span class="analytics-rank-name">{{ $product['name'] }}</span>
                                        <span class="analytics-rank-meta">r {{ number_format((float) ($product['r'] ?? 0), 2, ',', '.') }} · {{ number_format((float) ($product['change_abs'] ?? 0), 1, ',', '.') }} unit</span>
                                    </div>
                                    <div class="analytics-bar-track" aria-hidden="true">
                                        <span class="analytics-bar-fill is-down" style="width: {{ max(8, $pct) }}%"></span>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        @endif

        <div class="card admin-panel">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title">Tren per produk</h2>
                    <p class="analytics-card-sub">Parameter statistik regresi dan korelasi mingguan per produk</p>
                </div>
            </div>
            @if (! empty($products))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Produk</th><th>Tren</th><th>Minggu</th><th>Kemiringan (β)</th><th>r</th><th>Perubahan</th><th>Rata-rata / minggu</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                @php
                                    $meanVal = $product['mean'] ?? null;
                                    $meanPct = $meanVal !== null ? round(((float) $meanVal / $maxMean) * 100) : 0;
                                    $code = $product['label_code'] ?? '';
                                @endphp
                                <tr>
                                    <td class="admin-cell-primary">{{ $product['name'] }}</td>
                                    <td>
                                        <span class="analytics-trend-label analytics-trend-label--{{ $code }}">
                                            @if ($code === 'naik') ↑ @elseif ($code === 'turun') ↓ @endif{{ $product['label'] }}
                                        </span>
                                        @if (! empty($product['strength']))
                                            <span class="analytics-note">({{ $product['strength'] }})</span>
                                        @endif
                                    </td>
                                    <td class="admin-cell-nowrap">{{ $product['n'] }}</td>
                                    <td class="admin-cell-nowrap">{{ ($product['beta'] ?? null) !== null ? number_format((float) $product['beta'], 2, ',', '.') : '—' }}</td>
                                    <td class="admin-cell-nowrap">{{ ($product['r'] ?? null) !== null ? number_format((float) $product['r'], 2, ',', '.') : '—' }}</td>
                                    <td class="admin-cell-nowrap">{{ ($product['change_abs'] ?? null) !== null ? number_format((float) $product['change_abs'], 1, ',', '.') : '—' }}</td>
                                    <td>
                                        <div class="analytics-table-bar-cell">
                                            <span>{{ $meanVal !== null ? number_format((float) $meanVal, 1, ',', '.') : '—' }}</span>
                                            @if ($meanVal !== null)
                                                <span class="analytics-inline-bar" aria-hidden="true">
                                                    <span class="analytics-inline-bar-fill @if ($code === 'naik') is-up @elseif ($code === 'turun') is-down @endif" style="width: {{ max(6, $meanPct) }}%"></span>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="analytics-note">Tidak ada produk untuk dianalisis pada periode ini.</p>
            @endif
        </div>

        @if (! empty($trend['warnings']))
            <div class="card admin-panel analytics-warnings">
                <h2 class="admin-section-title">Catatan data</h2>
                <ul>
                    @foreach ($trend['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection
