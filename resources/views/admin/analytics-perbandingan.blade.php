{{-- M6 FR-04 Perbandingan Periode (ANL-41..46): metrik A vs B + grafik
garis pendapatan harian A vs B + produk perubahan terbesar + tabel per
produk. Panjang berbeda atau tumpang tindih tidak ditolak (ANL-42) —
modul memberi peringatan di `warnings`. Total Rp hanya deskriptif
(ANL-43); uji binomial eksak dua sisi untuk unit/transaksi/pembelian. --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <header class="admin-head">
        <div>
            <p class="admin-eyebrow">Analitik &bull; Perbandingan</p>
            <h1 class="admin-title">Perbandingan Periode</h1>
            <p class="admin-subtitle">Periode A {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} dibanding periode B {{ \Illuminate\Support\Carbon::parse($pembanding_dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($pembanding_sampai)->format('d M Y') }} (WITA).</p>
        </div>
    </header>

    @include('admin._analytics-compare-filter')

    @if ($unavailable)
        @include('admin._analytics-unavailable', ['config_error' => $config_error ?? false])
    @elseif (($compare['empty'] ?? false) === true)
        <div class="card admin-panel analytics-empty">
            <div class="analytics-state-icon is-muted" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M3 3V19C3 20.1046 3.89543 21 5 21H21M7 14L11 10L15 14L20 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="analytics-state-body">
                <h2 class="admin-section-title">Belum ada data pada kedua periode</h2>
                <p class="analytics-note">Kedua periode tidak memiliki data pada filter ini. Ubah periode di atas, atau isi data lewat Sistem Asli dan panel Developer.</p>
            </div>
        </div>
    @else
        @php
            // Bentuk respons compare_periods (rencana M6 §2): tiap metrik di
            // totals = compare_metric + `available`; transactions bisa
            // berbentuk {available: false, text, reason} bila order_totals
            // tidak dikirim. `transactions_differ_from_purchases` ada DI
            // DALAM totals (bukan di tingkat atas).
            $totals = $compare['totals'] ?? [];
            $metricCards = [
                ['key' => 'revenue_idr', 'label' => 'Penjualan', 'rupiah' => true, 'icon' => 'money'],
                ['key' => 'transactions', 'label' => 'Transaksi', 'rupiah' => false, 'icon' => 'receipt'],
                ['key' => 'units_sold', 'label' => 'Unit terjual', 'rupiah' => false, 'icon' => 'cube'],
                ['key' => 'purchases', 'label' => 'Pembelian', 'rupiah' => false, 'icon' => 'bag'],
            ];
            $fmtVal = function ($value, $rupiah) {
                if ($value === null) {
                    return '—';
                }
                return $rupiah
                    ? 'Rp '.number_format((int) $value, 0, ',', '.')
                    : number_format((int) $value);
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
            $lengthsDiffer = $daysA !== $daysB;
            $byProduct = $compare['by_product'] ?? [];
            $maxProductUnits = ! empty($byProduct)
                ? max(1, max(array_map(fn ($p) => max((int) ($p['units_sold']['a'] ?? 0), (int) ($p['units_sold']['b'] ?? 0)), $byProduct)))
                : 1;
        @endphp

        <div class="analytics-kpi-grid">
            @foreach ($metricCards as $card)
                @php
                    $m = $totals[$card['key']] ?? [];
                    $valA = (int) ($m['a'] ?? 0);
                    $valB = (int) ($m['b'] ?? 0);
                    $maxAB = max(1, $valA, $valB);
                    $pctA = round(($valA / $maxAB) * 100);
                    $pctB = round(($valB / $maxAB) * 100);
                    $changeVal = (float) ($m['change'] ?? 0);
                    $dirClass = $changeVal > 0 ? 'is-up' : ($changeVal < 0 ? 'is-down' : 'is-flat');
                @endphp
                <div class="card analytics-kpi">
                    <div class="analytics-kpi-head">
                        <span class="analytics-kpi-label">{{ $card['label'] }}</span>
                        <span class="analytics-kpi-icon" aria-hidden="true">
                            @if ($card['icon'] === 'money')
                                <svg viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4.5" width="15" height="11" rx="2.5" stroke="currentColor" stroke-width="1.6"/><circle cx="10" cy="10" r="2.25" stroke="currentColor" stroke-width="1.6"/></svg>
                            @elseif ($card['icon'] === 'receipt')
                                <svg viewBox="0 0 20 20" fill="none"><path d="M5 3.5H15C15.8284 3.5 16.5 4.17157 16.5 5V16.5L14 15L11.5 16.5L10 15L8.5 16.5L6 15L3.5 16.5V5C3.5 4.17157 4.17157 3.5 5 3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M7 8H13M7 11H11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            @elseif ($card['icon'] === 'bag')
                                <svg viewBox="0 0 20 20" fill="none"><path d="M5 6.5H15L14.2 15.5C14.1 16.3 13.4 17 12.6 17H7.4C6.6 17 5.9 16.3 5.8 15.5L5 6.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M7.5 6.5V5C7.5 3.61929 8.61929 2.5 10 2.5C11.3807 2.5 12.5 3.61929 12.5 5V6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            @else
                                <svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5L16.5 6.25V13.75L10 17.5L3.5 13.75V6.25L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3.5 6.25L10 10L16.5 6.25M10 10V17.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                            @endif
                        </span>
                    </div>
                    @if (($m['available'] ?? true) === false)
                        <span class="analytics-kpi-value">{{ $m['text'] ?? 'Belum dapat dihitung' }}</span>
                        <span class="analytics-kpi-sub">{{ $m['reason'] ?? '' }}</span>
                    @else
                        <span class="analytics-kpi-value">{{ $fmtVal($m['a'] ?? null, $card['rupiah']) }}</span>
                        <div class="analytics-ab-bars" aria-hidden="true">
                            <div class="analytics-ab-bar-row">
                                <span class="analytics-ab-tag">A</span>
                                <div class="analytics-bar-track"><span class="analytics-bar-fill" style="width: {{ max(6, $pctA) }}%"></span></div>
                            </div>
                            <div class="analytics-ab-bar-row">
                                <span class="analytics-ab-tag is-b">B</span>
                                <div class="analytics-bar-track"><span class="analytics-bar-fill is-muted" style="width: {{ max(6, $pctB) }}%"></span></div>
                            </div>
                        </div>
                        <span class="analytics-kpi-sub">Periode A ({{ $daysA }} hari) &bull; Periode B: <strong>{{ $fmtVal($m['b'] ?? null, $card['rupiah']) }}</strong> ({{ $daysB }} hari)</span>
                        <span class="analytics-kpi-sub analytics-kpi-change {{ $dirClass }}">
                            <span class="analytics-kpi-change-main">
                                @if ($changeVal > 0)
                                    <svg class="analytics-kpi-trend-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 11.5L12.5 4.5M12.5 4.5H6.5M12.5 4.5V10.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @elseif ($changeVal < 0)
                                    <svg class="analytics-kpi-trend-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 4.5L12.5 11.5M12.5 11.5H6.5M12.5 11.5V5.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @else
                                    <svg class="analytics-kpi-trend-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8H13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                                @endif
                                <span>Selisih {{ $fmtSigned($m['change'] ?? null, $card['rupiah']) }} ({{ $fmtPct($m['change_ratio'] ?? null) ?? ($m['change_ratio_text'] ?? 'Belum dapat dihitung') }})</span>
                            </span>
                            @if (! empty($m['significance']))
                                <span class="analytics-sig-badge {{ ($m['significant'] ?? null) === true ? 'is-sig' : 'is-insig' }}">&bull; {{ $m['significance'] }}</span>
                            @endif
                        </span>
                        <span class="analytics-kpi-sub">Per hari: {{ $fmtPerDay($m['per_day_change'] ?? null, $card['rupiah']) }} ({{ $fmtPct($m['per_day_change_ratio'] ?? null) ?? ($m['per_day_change_ratio_text'] ?? 'Belum dapat dihitung') }})</span>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($lengthsDiffer)
            <div class="analytics-insight-bar is-warn" role="status">
                <svg class="analytics-insight-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M10 6.5V10.5M10 13.5H10.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <p class="analytics-note">Panjang periode berbeda (A: <strong>{{ $daysA }} hari</strong>, B: <strong>{{ $daysB }} hari</strong>) — bandingkan perubahan per hari, bukan hanya persen mentah.</p>
            </div>
        @endif

        <div class="analytics-footnotes">
            @if (($totals['revenue_idr']['descriptive_only'] ?? false) === true)
                <p class="analytics-note">* Total penjualan (Rp) hanya deskriptif, bukan klaim tren.</p>
            @endif
            @if (($totals['transactions_differ_from_purchases'] ?? null) === true)
                <p class="analytics-note">Jumlah transaksi berbeda dari pembelian karena satu pesanan dapat berisi beberapa item.</p>
            @endif
            @if (! empty($compare['caveats']))
                @foreach ($compare['caveats'] as $caveat)
                    <p class="analytics-note">{{ $caveat }}</p>
                @endforeach
            @endif
        </div>

        @if ($chart)
            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Pendapatan harian A vs B</h2>
                        <p class="analytics-card-sub">Sumbu-x = hari ke-n dalam tiap periode &bull; Pembayaran berhasil</p>
                    </div>
                    <div class="analytics-legend-group">
                        <span class="analytics-legend-pill">
                            <span class="analytics-legend-dot" aria-hidden="true"></span>
                            Periode A ({{ $daysA }} hari)
                        </span>
                        <span class="analytics-legend-pill">
                            <span class="analytics-legend-dot is-neutral" aria-hidden="true"></span>
                            Periode B ({{ $daysB }} hari)
                        </span>
                    </div>
                </div>
                <div class="analytics-chart-surface">
                    <div class="chart-wrap"><canvas data-chart data-chart-rupiah data-chart-config='@json($chart)' aria-label="Grafik pendapatan harian periode A dan B" role="img"></canvas></div>
                </div>
            </div>
        @endif

        @php
            $increase = $compare['biggest_increase'] ?? [];
            $decrease = $compare['biggest_decrease'] ?? [];
        @endphp
        @if (! empty($increase) || ! empty($decrease))
            <div class="analytics-split-grid">
                @if (! empty($increase))
                    @php $maxInc = max(1.0, max(array_map(fn ($p) => abs((float) ($p['per_day_change'] ?? 0)), $increase))); @endphp
                    <div class="card admin-panel">
                        <div class="analytics-card-head">
                            <div>
                                <h2 class="admin-section-title">Perubahan terbesar naik</h2>
                                <p class="analytics-card-sub">Urut selisih unit per hari (ANL-45)</p>
                            </div>
                        </div>
                        <ol class="analytics-list analytics-rank-list">
                            @foreach ($increase as $product)
                                @php $pct = round((abs((float) ($product['per_day_change'] ?? 0)) / $maxInc) * 100); @endphp
                                <li class="analytics-rank-item">
                                    <div class="analytics-rank-row">
                                        <span class="analytics-rank-name">{{ $product['name'] }}</span>
                                        <span class="analytics-rank-meta">A: {{ number_format((int) ($product['units_a'] ?? 0)) }} unit &bull; B: {{ number_format((int) ($product['units_b'] ?? 0)) }} unit &bull; {{ $fmtPerDay($product['per_day_change'] ?? null, false) }}</span>
                                    </div>
                                    <div class="analytics-bar-track" aria-hidden="true">
                                        <span class="analytics-bar-fill is-up" style="width: {{ max(8, $pct) }}%"></span>
                                    </div>
                                    @if (! empty($product['significance']))
                                        <span class="analytics-note">{{ $product['significance'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @if (! empty($decrease))
                    @php $maxDec = max(1.0, max(array_map(fn ($p) => abs((float) ($p['per_day_change'] ?? 0)), $decrease))); @endphp
                    <div class="card admin-panel">
                        <div class="analytics-card-head">
                            <div>
                                <h2 class="admin-section-title">Perubahan terbesar turun</h2>
                                <p class="analytics-card-sub">Urut selisih unit per hari (ANL-45)</p>
                            </div>
                        </div>
                        <ol class="analytics-list analytics-rank-list">
                            @foreach ($decrease as $product)
                                @php $pct = round((abs((float) ($product['per_day_change'] ?? 0)) / $maxDec) * 100); @endphp
                                <li class="analytics-rank-item">
                                    <div class="analytics-rank-row">
                                        <span class="analytics-rank-name">{{ $product['name'] }}</span>
                                        <span class="analytics-rank-meta">A: {{ number_format((int) ($product['units_a'] ?? 0)) }} unit &bull; B: {{ number_format((int) ($product['units_b'] ?? 0)) }} unit &bull; {{ $fmtPerDay($product['per_day_change'] ?? null, false) }}</span>
                                    </div>
                                    <div class="analytics-bar-track" aria-hidden="true">
                                        <span class="analytics-bar-fill is-down" style="width: {{ max(8, $pct) }}%"></span>
                                    </div>
                                    @if (! empty($product['significance']))
                                        <span class="analytics-note">{{ $product['significance'] }}</span>
                                    @endif
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
                    <h2 class="admin-section-title">Perbandingan per produk</h2>
                    <p class="analytics-card-sub">Unit terjual A vs B beserta uji binomial eksak dua sisi (p &lt; 0,05 → "Berbeda dari variasi acak")</p>
                </div>
            </div>
            @if (! empty($byProduct))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Produk</th><th>Unit A</th><th>Unit B</th><th>Perubahan/hari</th><th>Perubahan</th><th>Signifikansi</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($byProduct as $product)
                                @php
                                    $units = $product['units_sold'] ?? [];
                                    $uA = (int) ($units['a'] ?? 0);
                                    $uB = (int) ($units['b'] ?? 0);
                                    $uPctA = round(($uA / $maxProductUnits) * 100);
                                    $isSig = ($units['significant'] ?? null) === true;
                                    $pDay = (float) ($units['per_day_change'] ?? 0);
                                @endphp
                                <tr>
                                    <td class="admin-cell-primary">{{ $product['name'] }}</td>
                                    <td class="admin-cell-nowrap">
                                        <div class="analytics-table-bar-cell">
                                            <span class="admin-cell-strong">{{ number_format($uA) }}</span>
                                            <span class="analytics-inline-bar" aria-hidden="true">
                                                <span class="analytics-inline-bar-fill @if ($pDay > 0) is-up @elseif ($pDay < 0) is-down @endif" style="width: {{ max(6, $uPctA) }}%"></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="admin-cell-nowrap">{{ number_format($uB) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtPerDay($units['per_day_change'] ?? null, false) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtPct($units['change_ratio'] ?? null) ?? ($units['change_ratio_text'] ?? 'Belum dapat dihitung') }}</td>
                                    <td>
                                        @if (! empty($units['significance']))
                                            <span class="analytics-trend-label {{ $isSig ? ($pDay >= 0 ? 'analytics-trend-label--naik' : 'analytics-trend-label--turun') : '' }}">{{ $units['significance'] }}</span>
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
                <p class="analytics-note">Tidak ada produk untuk dibandingkan pada kedua periode ini.</p>
            @endif
        </div>

        @if (! empty($compare['warnings']))
            <div class="card admin-panel analytics-warnings">
                <h2 class="admin-section-title">Catatan data</h2>
                <ul>
                    @foreach ($compare['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection
