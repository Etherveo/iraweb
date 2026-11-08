{{-- M7 FR-06 Kategori & Karakteristik (ANL-61..65): performa per kategori +
porsi (largest remainder, jumlah tepat 100,0%), grafik unit per kategori
(ANL-62), kelompok harga (ANL-64), matriks kategori x dua periode (ANL-63,
reuse partial filter dua periode). Kategori tanpa transaksi tetap tampil
(ANL-65) — jangan filter has_sales: false. Tren kategori memakai algoritma
FR-02 (BH atas kategori); periode < 8 minggu penuh menampilkan "Data belum
cukup" (bukan bug, sama seperti /admin/tren). Transaksi per kelompok harga
selalu "Belum dapat dihitung" — modul tidak menghitungnya. --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <header class="admin-head">
        <div>
            <p class="admin-eyebrow">Analitik &bull; Kategori</p>
            <h1 class="admin-title">Kategori &amp; Karakteristik</h1>
            <p class="admin-subtitle">Periode A {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} dibanding periode B {{ \Illuminate\Support\Carbon::parse($pembanding_dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($pembanding_sampai)->format('d M Y') }} (WITA).</p>
        </div>
    </header>

    @include('admin._analytics-compare-filter', ['compareRoute' => 'admin.kategori-analisis'])

    @if ($unavailable)
        @include('admin._analytics-unavailable', ['config_error' => $config_error ?? false])
    @elseif (($empty ?? false) === true)
        <div class="card admin-panel analytics-empty">
            <div class="analytics-state-icon is-muted" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M3 3V19C3 20.1046 3.89543 21 5 21H21M7 14L11 10L15 14L20 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="analytics-state-body">
                <h2 class="admin-section-title">Belum ada data pada periode ini</h2>
                <p class="analytics-note">Tidak ada penjualan pada periode ini. Ubah periode di atas, atau isi data lewat Sistem Asli dan panel Developer.</p>
            </div>
        </div>
    @else
        @php
            // Bentuk respons analyze_category (rencana M7 §2, dibaca dari
            // category.py): totals {revenue_idr, units_sold, purchases,
            // transactions (+transactions_text bila null)}, categories[]
            // {category_id, name, revenue_idr, units_sold, purchases,
            // transactions (+transactions_text), share_revenue/share_units
            // ({tenths, pct} atau null bila total 0), has_sales,
            // series[] {bucket_start, bucket_end, partial, revenue_idr,
            // units_sold}, trend ({label_code, label, strength, n, beta,
            // r, p, q, change_abs, avg_units_per_week, series,
            // start_source, start_date, trimmed_weeks} atau null untuk
            // "Tanpa kategori")}, price_groups[] {label, min_price,
            // max_price_exclusive, product_count, active_product_count,
            // revenue_idr, units_sold, purchases, transactions (selalu
            // null) + transactions_text, share_revenue, share_units}.
            // compare_categories: categories[] {category_id, name,
            // revenue_idr/units_sold = compare_metric (Rp +
            // descriptive_only)} + period_a/period_b {days} + warnings.
            $totals = $category['totals'] ?? [];
            $categories = $category['categories'] ?? [];
            $priceGroups = $category['price_groups'] ?? [];
            $matrix = $compare['categories'] ?? [];
            $fmtRp = fn ($v) => $v === null ? '—' : 'Rp '.number_format((int) $v, 0, ',', '.');
            $fmtNum = fn ($v) => $v === null ? '—' : number_format((int) $v);
            $fmtShare = function ($share) {
                if ($share === null || ! isset($share['pct'])) {
                    return '—';
                }
                return number_format((float) $share['pct'], 1, ',', '.').'%';
            };
            $fmtSigned = function ($value, $rupiah) {
                if ($value === null) {
                    return '—';
                }
                $sign = $value > 0 ? '+' : '';
                return $sign.($rupiah
                    ? 'Rp '.number_format((int) $value, 0, ',', '.')
                    : number_format((int) $value));
            };
            $fmtPct = function ($ratio) {
                if ($ratio === null) {
                    return null;
                }
                $sign = $ratio > 0 ? '+' : '';
                return $sign.number_format((float) $ratio * 100, 1, ',', '.').'%';
            };
            $fmtPerDay = function ($value, $rupiah) {
                if ($value === null) {
                    return '—';
                }
                $sign = $value > 0 ? '+' : '';
                $num = number_format((float) $value, 1, ',', '.');
                return $sign.($rupiah ? 'Rp '.$num : $num).'/hari';
            };
            $daysA = (int) ($compare['period_a']['days'] ?? 0);
            $daysB = (int) ($compare['period_b']['days'] ?? 0);
            $lengthsDiffer = $compare !== null && $daysA !== $daysB;
            $warnings = array_values(array_unique(array_merge(
                $category['warnings'] ?? [],
                $compare['warnings'] ?? [],
            )));
        @endphp

        <div class="analytics-kpi-grid">
            <div class="card analytics-kpi">
                <span class="analytics-kpi-label">Penjualan</span>
                <span class="analytics-kpi-value">{{ $fmtRp($totals['revenue_idr'] ?? null) }}</span>
                <span class="analytics-kpi-sub">Total periode A</span>
            </div>
            <div class="card analytics-kpi">
                <span class="analytics-kpi-label">Transaksi</span>
                <span class="analytics-kpi-value">{{ ($totals['transactions'] ?? null) === null ? ($totals['transactions_text'] ?? 'Belum dapat dihitung') : number_format((int) $totals['transactions']) }}</span>
                <span class="analytics-kpi-sub">Pesanan dibayar (unik)</span>
            </div>
            <div class="card analytics-kpi">
                <span class="analytics-kpi-label">Unit terjual</span>
                <span class="analytics-kpi-value">{{ $fmtNum($totals['units_sold'] ?? null) }}</span>
                <span class="analytics-kpi-sub">Total periode A</span>
            </div>
            <div class="card analytics-kpi">
                <span class="analytics-kpi-label">Pembelian</span>
                <span class="analytics-kpi-value">{{ $fmtNum($totals['purchases'] ?? null) }}</span>
                <span class="analytics-kpi-sub">Item dibayar</span>
            </div>
        </div>

        @if ($chart)
            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Unit terjual per kategori</h2>
                        <p class="analytics-card-sub">Garis unit per bucket (ANL-62) &bull; Granularitas {{ $category['granularity'] ?? 'auto' }}</p>
                    </div>
                </div>
                <div class="analytics-chart-surface">
                    <div class="chart-wrap"><canvas data-chart data-chart-config='@json($chart)' aria-label="Grafik unit terjual per kategori" role="img"></canvas></div>
                </div>
            </div>
        @endif

        <div class="card admin-panel">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title">Performa per kategori</h2>
                    <p class="analytics-card-sub">Porsi Rp dan unit memakai largest remainder — jumlah tepat 100,0% &bull; Kategori tanpa penjualan tetap tampil (ANL-65)</p>
                </div>
            </div>
            @if (! empty($categories))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Kategori</th><th>Penjualan</th><th>Porsi Rp</th><th>Unit</th><th>Porsi unit</th><th>Pembelian</th><th>Transaksi</th><th>Tren</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $cat)
                                @php $trend = $cat['trend'] ?? null; @endphp
                                <tr>
                                    <td class="admin-cell-primary">{{ $cat['name'] }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtRp($cat['revenue_idr'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtShare($cat['share_revenue'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtNum($cat['units_sold'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtShare($cat['share_units'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtNum($cat['purchases'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ ($cat['transactions'] ?? null) === null ? ($cat['transactions_text'] ?? 'Belum dapat dihitung') : number_format((int) $cat['transactions']) }}</td>
                                    <td>
                                        @if ($trend !== null && ! empty($trend['label']))
                                            @php $code = $trend['label_code'] ?? ''; @endphp
                                            <span class="analytics-trend-label analytics-trend-label--{{ $code }}">
                                                @if ($code === 'naik') ↑ @elseif ($code === 'turun') ↓ @endif{{ $trend['label'] }}@if (! empty($trend['strength'])) ({{ $trend['strength'] }})@endif
                                            </span>
                                            @if (($trend['beta'] ?? null) !== null)
                                                <span class="analytics-note">β {{ number_format((float) $trend['beta'], 2, ',', '.') }} &bull; r {{ number_format((float) ($trend['r'] ?? 0), 2, ',', '.') }}</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="analytics-note">Tidak ada kategori pada periode ini.</p>
            @endif
        </div>

        <div class="card admin-panel">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title">Kelompok harga</h2>
                    <p class="analytics-card-sub">Batas kelompok tetap (ANL-64) &bull; Karakteristik lain belum tersedia sampai atribut produk ditambah</p>
                </div>
            </div>
            @if (! empty($priceGroups))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Kelompok harga</th><th>Produk</th><th>Penjualan</th><th>Porsi Rp</th><th>Unit</th><th>Porsi unit</th><th>Pembelian</th><th>Transaksi</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($priceGroups as $group)
                                <tr>
                                    <td class="admin-cell-primary">{{ $group['label'] }}</td>
                                    <td class="admin-cell-nowrap">{{ number_format((int) ($group['active_product_count'] ?? 0)) }} aktif / {{ number_format((int) ($group['product_count'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtRp($group['revenue_idr'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtShare($group['share_revenue'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtNum($group['units_sold'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtShare($group['share_units'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtNum($group['purchases'] ?? null) }}</td>
                                    <td class="admin-cell-nowrap">{{ $group['transactions_text'] ?? 'Belum dapat dihitung' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="analytics-note">Tidak ada kelompok harga pada periode ini.</p>
            @endif
        </div>

        @if ($compare !== null)
            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Perbandingan kategori antar periode</h2>
                        <p class="analytics-card-sub">Matriks kategori × dua periode (ANL-63) &bull; Periode A {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }}, periode B {{ \Illuminate\Support\Carbon::parse($pembanding_dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($pembanding_sampai)->format('d M Y') }}</p>
                    </div>
                </div>
                @if ($lengthsDiffer)
                    <div class="analytics-insight-bar is-warn" role="status">
                        <p class="analytics-note">Panjang periode berbeda (A: <strong>{{ $daysA }} hari</strong>, B: <strong>{{ $daysB }} hari</strong>) — bandingkan perubahan per hari, bukan hanya persen mentah.</p>
                    </div>
                @endif
                @if (! empty($matrix))
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Kategori</th><th>Rp A</th><th>Rp B</th><th>Selisih Rp</th><th>% Rp</th><th>Unit A</th><th>Unit B</th><th>Perubahan/hari</th><th>Perubahan unit</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($matrix as $row)
                                    @php
                                        $rev = $row['revenue_idr'] ?? [];
                                        $units = $row['units_sold'] ?? [];
                                    @endphp
                                    <tr>
                                        <td class="admin-cell-primary">{{ $row['name'] }}</td>
                                        <td class="admin-cell-nowrap">{{ $fmtRp($rev['a'] ?? null) }}</td>
                                        <td class="admin-cell-nowrap">{{ $fmtRp($rev['b'] ?? null) }}</td>
                                        <td class="admin-cell-nowrap">{{ $fmtSigned($rev['change'] ?? null, true) }}</td>
                                        <td class="admin-cell-nowrap">{{ $fmtPct($rev['change_ratio'] ?? null) ?? ($rev['change_ratio_text'] ?? 'Belum dapat dihitung') }}</td>
                                        <td class="admin-cell-nowrap">{{ $fmtNum($units['a'] ?? null) }}</td>
                                        <td class="admin-cell-nowrap">{{ $fmtNum($units['b'] ?? null) }}</td>
                                        <td class="admin-cell-nowrap">{{ $fmtPerDay($units['per_day_change'] ?? null, false) }}</td>
                                        <td class="admin-cell-nowrap">{{ $fmtPct($units['change_ratio'] ?? null) ?? ($units['change_ratio_text'] ?? 'Belum dapat dihitung') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="analytics-footnotes">
                        <p class="analytics-note">* Total penjualan (Rp) hanya deskriptif, bukan klaim tren.</p>
                    </div>
                @else
                    <p class="analytics-note">Tidak ada kategori untuk dibandingkan pada kedua periode ini.</p>
                @endif
            </div>
        @endif

        @if (! empty($warnings))
            <div class="card admin-panel analytics-warnings">
                <h2 class="admin-section-title">Catatan data</h2>
                <ul>
                    @foreach ($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection
