{{-- M5 FR-03 Funnel (I3): dilihat → keranjang → booking → pembelian
(pembelian = dibayar, keputusan 2026-11-08). Angka memakai facts yang
sama dengan FR-01/FR-02, jadi konsisten dengan dashboard dan tren. --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <header class="admin-head">
        <div>
            <p class="admin-eyebrow">Analitik &bull; Funnel</p>
            <h1 class="admin-title">Funnel Pembelian</h1>
            <p class="admin-subtitle">Alur dilihat → keranjang → booking → pembelian (dibayar), {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} (WITA).</p>
        </div>
    </header>

    @include('admin._analytics-filter', ['filterRoute' => 'admin.funnel'])

    @if ($unavailable)
        @include('admin._analytics-unavailable', ['config_error' => $config_error ?? false])
    @else
        @php
            $stages = $funnel['stages'] ?? [];
            $maxStageCount = ! empty($stages) ? max(1, max(array_map(fn ($s) => (int) ($s['count'] ?? 0), $stages))) : 1;
        @endphp

        @if ($has_events)
            <div class="analytics-kpi-grid analytics-funnel-steps">
                @foreach ($stages as $idx => $stage)
                    @php
                        $pctView = ($stage['ratio_view'] ?? null) !== null
                            ? number_format((float) $stage['ratio_view'] * 100, 1, ',', '.').'%'
                            : ($stage['ratio_view_text'] ?? '—');
                        $barWidth = round(((int) ($stage['count'] ?? 0) / $maxStageCount) * 100);
                    @endphp
                    <div class="card analytics-kpi analytics-funnel-step">
                        <div class="analytics-kpi-head">
                            <span class="analytics-kpi-label">Tahap {{ $idx + 1 }} &bull; {{ $stage['label'] }}</span>
                            <span class="analytics-step-badge">{{ $pctView }}</span>
                        </div>
                        <span class="analytics-kpi-value">{{ number_format((int) $stage['count']) }}</span>
                        <div class="analytics-bar-track" aria-hidden="true">
                            <span class="analytics-bar-fill" style="width: {{ max(6, $barWidth) }}%"></span>
                        </div>
                        <span class="analytics-kpi-sub">
                            @if ($idx === 0)
                                Titik awal alur kunjungan
                            @else
                                {{ ($stage['ratio_prev'] ?? null) !== null ? number_format((float) $stage['ratio_prev'] * 100, 1, ',', '.').'% dari tahap sebelumnya' : ($stage['ratio_prev_text'] ?? '—') }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($has_events && $chart)
            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Grafik funnel</h2>
                        <p class="analytics-card-sub">Perbandingan jumlah aktivitas di setiap tahap konversi</p>
                    </div>
                </div>
                <div class="analytics-chart-surface">
                    <div class="chart-wrap"><canvas data-chart data-chart-config='@json($chart)' aria-label="Grafik funnel pembelian" role="img"></canvas></div>
                </div>
            </div>
        @endif

        <div class="card admin-panel">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title">Tahap funnel</h2>
                    <p class="analytics-card-sub">Rasio konversi antar-tahap dan terhadap total produk dilihat</p>
                </div>
            </div>
            @if ($has_events)
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Tahap</th><th>Jumlah</th><th>vs tahap sebelumnya</th><th>vs dilihat</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($stages as $stage)
                                @php
                                    $ratioViewNum = ($stage['ratio_view'] ?? null) !== null ? round((float) $stage['ratio_view'] * 100) : 0;
                                @endphp
                                <tr>
                                    <td class="admin-cell-primary">{{ $stage['label'] }}@if ($stage['anomaly'] ?? false) <span class="analytics-note">(catatan data)</span>@endif</td>
                                    <td class="admin-cell-strong">{{ number_format((int) $stage['count']) }}</td>
                                    <td class="admin-cell-nowrap">{{ ($stage['ratio_prev'] ?? null) !== null ? number_format((float) $stage['ratio_prev'] * 100, 1, ',', '.').'%' : ($stage['ratio_prev_text'] ?? '—') }}</td>
                                    <td>
                                        <div class="analytics-table-bar-cell">
                                            <span>{{ ($stage['ratio_view'] ?? null) !== null ? number_format((float) $stage['ratio_view'] * 100, 1, ',', '.').'%' : ($stage['ratio_view_text'] ?? '—') }}</span>
                                            @if (($stage['ratio_view'] ?? null) !== null)
                                                <span class="analytics-inline-bar" aria-hidden="true">
                                                    <span class="analytics-inline-bar-fill" style="width: {{ max(6, min(100, $ratioViewNum)) }}%"></span>
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
                <p class="analytics-note">Belum ada aktivitas funnel pada periode ini.</p>
            @endif

            <div class="analytics-callout-grid">
                @if (! empty($funnel['largest_drop']))
                    @php $drop = $funnel['largest_drop']; @endphp
                    <div class="analytics-insight-bar is-warn">
                        <svg class="analytics-insight-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M4 6L10 12L13 9L16.5 13.5M16.5 13.5H12.5M16.5 13.5V9.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <p class="analytics-note">Penurunan terbesar: <strong>{{ $drop['from_label'] }} → {{ $drop['to_label'] }}</strong> ({{ number_format((int) $drop['diff']) }} event, {{ number_format((float) $drop['ratio'] * 100, 1, ',', '.') }}% dari {{ $drop['from_label'] }}).</p>
                    </div>
                @endif
                <div class="analytics-insight-bar">
                    <svg class="analytics-insight-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M10 6.5V10.5M10 13.5H10.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <p class="analytics-note">Booking yang belum dibeli: <strong>{{ number_format((int) ($funnel['bookings_not_purchased'] ?? 0)) }}</strong>. Pembatalan: <strong>{{ number_format((int) ($funnel['cancels'] ?? 0)) }}</strong>.</p>
                </div>
            </div>

            @if (! empty($funnel['anomalies']))
                <ul class="analytics-note-list">
                    @foreach ($funnel['anomalies'] as $anomaly)
                        <li>{{ $anomaly['note'] }} ({{ number_format((int) $anomaly['from_count']) }} → {{ number_format((int) $anomaly['to_count']) }}).</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="card admin-panel">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title">Funnel per produk</h2>
                    <p class="analytics-card-sub">Rincian aktivitas dilihat hingga pembelian berbayar untuk tiap produk</p>
                </div>
            </div>
            @if (! empty($funnel['per_product']))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Produk</th><th>Dilihat</th><th>Keranjang</th><th>Booking</th><th>Pembelian</th><th>Konversi</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($funnel['per_product'] as $product)
                                @php
                                    // Jumlah per produk ada di 'counts' (bukan di tingkat
                                    // atas); konversi dihitung dari counts itu.
                                    $counts = $product['counts'] ?? [];
                                    $conv = ((int) ($counts['views'] ?? 0)) > 0
                                        ? ((float) ($counts['purchases'] ?? 0)) / ((float) $counts['views'])
                                        : null;
                                    $convPct = $conv !== null ? round($conv * 100) : 0;
                                @endphp
                                <tr>
                                    <td class="admin-cell-primary">{{ $product['name'] }}</td>
                                    <td class="admin-cell-nowrap">{{ number_format((int) ($counts['views'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap">{{ number_format((int) ($counts['add_to_carts'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap">{{ number_format((int) ($counts['bookings'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap admin-cell-strong">{{ number_format((int) ($counts['purchases'] ?? 0)) }}</td>
                                    <td>
                                        <div class="analytics-table-bar-cell">
                                            <span>{{ $conv !== null ? number_format($conv * 100, 1, ',', '.').'%' : '—' }}</span>
                                            @if ($conv !== null)
                                                <span class="analytics-inline-bar" aria-hidden="true">
                                                    <span class="analytics-inline-bar-fill" style="width: {{ max(6, min(100, $convPct)) }}%"></span>
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
                <p class="analytics-note">Tidak ada aktivitas funnel pada periode ini.</p>
            @endif
        </div>

        @if (! empty($funnel['warnings']))
            <div class="card admin-panel analytics-warnings">
                <h2 class="admin-section-title">Catatan data</h2>
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
