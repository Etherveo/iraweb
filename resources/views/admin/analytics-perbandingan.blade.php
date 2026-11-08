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
                ['key' => 'revenue_idr', 'label' => 'Penjualan', 'rupiah' => true],
                ['key' => 'transactions', 'label' => 'Transaksi', 'rupiah' => false],
                ['key' => 'units_sold', 'label' => 'Unit terjual', 'rupiah' => false],
                ['key' => 'purchases', 'label' => 'Pembelian', 'rupiah' => false],
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
        @endphp

        <div class="analytics-kpi-grid">
            @foreach ($metricCards as $card)
                @php $m = $totals[$card['key']] ?? []; @endphp
                <div class="card analytics-kpi">
                    <div class="analytics-kpi-head">
                        <span class="analytics-kpi-label">{{ $card['label'] }}</span>
                        <span class="analytics-kpi-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5L16.5 6.25V13.75L10 17.5L3.5 13.75V6.25L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3.5 6.25L10 10L16.5 6.25M10 10V17.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                        </span>
                    </div>
                    @if (($m['available'] ?? true) === false)
                        <span class="analytics-kpi-value">{{ $m['text'] ?? 'Belum dapat dihitung' }}</span>
                        <span class="analytics-kpi-sub">{{ $m['reason'] ?? '' }}</span>
                    @else
                        <span class="analytics-kpi-value">{{ $fmtVal($m['a'] ?? null, $card['rupiah']) }}</span>
                        <span class="analytics-kpi-sub">Periode A ({{ $daysA }} hari) &bull; Periode B: {{ $fmtVal($m['b'] ?? null, $card['rupiah']) }} ({{ $daysB }} hari)</span>
                        <span class="analytics-kpi-sub analytics-kpi-change {{ (($m['change_ratio'] ?? 0) > 0) ? 'is-up' : ((($m['change_ratio'] ?? 0) < 0) ? 'is-down' : 'is-flat') }}">
                            <span class="analytics-kpi-change-main">
                                <span>Selisih {{ $fmtSigned($m['change'] ?? null, $card['rupiah']) }} ({{ $fmtPct($m['change_ratio'] ?? null) ?? ($m['change_ratio_text'] ?? 'Belum dapat dihitung') }})</span>
                            </span>
                            @if (! empty($m['significance']))
                                <span class="analytics-sig-badge {{ ($m['significant'] ?? null) === true ? 'is-sig' : 'is-insig' }}">{{ $m['significance'] }}</span>
                            @endif
                        </span>
                        <span class="analytics-kpi-sub">Per hari: {{ $fmtPerDay($m['per_day_change'] ?? null, $card['rupiah']) }} ({{ $fmtPct($m['per_day_change_ratio'] ?? null) ?? ($m['per_day_change_ratio_text'] ?? 'Belum dapat dihitung') }})</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="analytics-footnotes">
            @if (($totals['revenue_idr']['descriptive_only'] ?? false) === true)
                <p class="analytics-note">* Total penjualan (Rp) hanya deskriptif, bukan klaim tren.</p>
            @endif
            @if (($totals['transactions_differ_from_purchases'] ?? null) === true)
                <p class="analytics-note">Jumlah transaksi berbeda dari pembelian karena satu pesanan dapat berisi beberapa item.</p>
            @endif
            @if ($lengthsDiffer)
                <p class="analytics-note">Panjang periode berbeda (A: {{ $daysA }} hari, B: {{ $daysB }} hari) — bandingkan perubahan per hari, bukan hanya persen mentah.</p>
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
                    <span class="analytics-legend-pill">
                        <span class="analytics-legend-dot" aria-hidden="true"></span>
                        Periode A &bull; Periode B
                    </span>
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
            @if (! empty($compare['by_product']))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Produk</th><th>Unit A</th><th>Unit B</th><th>Perubahan/hari</th><th>Perubahan</th><th>Signifikansi</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($compare['by_product'] as $product)
                                @php $units = $product['units_sold'] ?? []; @endphp
                                <tr>
                                    <td class="admin-cell-primary">{{ $product['name'] }}</td>
                                    <td class="admin-cell-nowrap">{{ number_format((int) ($units['a'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap">{{ number_format((int) ($units['b'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtPerDay($units['per_day_change'] ?? null, false) }}</td>
                                    <td class="admin-cell-nowrap">{{ $fmtPct($units['change_ratio'] ?? null) ?? ($units['change_ratio_text'] ?? 'Belum dapat dihitung') }}</td>
                                    <td>{{ $units['significance'] ?? '—' }}</td>
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
