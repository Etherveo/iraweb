{{-- M6 FR-05 Aktivitas & Minat (ANL-51..56): ringkasan aktivitas +
sorotan "Perlu ditinjau" (ANL-53) + pembanding (ANL-56) + tabel minat
per produk (ANL-54, dapat diurutkan dan difilter kategori). Baris
sorotan menaut ke riwayat yang sudah terfilter pada produk itu
(ANL-55). Label status berasal dari modul ("Perlu ditinjau" /
"Data belum cukup" / "Tidak disorot"). --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <header class="admin-head">
        <div>
            <p class="admin-eyebrow">Analitik &bull; Aktivitas &amp; Minat</p>
            <h1 class="admin-title">Aktivitas &amp; Minat</h1>
            <p class="admin-subtitle">Aktivitas kunjungan dan minat per produk, {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} (WITA).</p>
        </div>
        <div class="admin-head-actions">
            <a class="btn-primary analytics-history-cta" href="{{ route('admin.aktivitas.riwayat') }}">
                <svg class="analytics-btn-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.7"/>
                    <path d="M10 6.25V10.25L12.75 12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Lihat Riwayat Aktivitas</span>
            </a>
        </div>
    </header>

    @include('admin._analytics-filter', ['filterRoute' => 'admin.aktivitas', 'categories' => $categories])

    @if ($unavailable)
        @include('admin._analytics-unavailable', ['config_error' => $config_error ?? false])
    @else
        @php
            $highlights = $interest['highlights'] ?? [];
            $comparators = $interest['comparators'] ?? [];
            $fmtPct = function ($ratio) {
                if ($ratio === null) {
                    return null;
                }
                return number_format((float) $ratio * 100, 1, ',', '.').'%';
            };
            $fmtInterval = function ($interval) use ($fmtPct) {
                if (! is_array($interval) || ($interval['low'] ?? null) === null || ($interval['high'] ?? null) === null) {
                    return '—';
                }
                return $fmtPct($interval['low']).' – '.$fmtPct($interval['high']);
            };
            $sortUrl = function ($key) {
                return route('admin.aktivitas', array_merge(request()->except('urut'), ['urut' => $key]));
            };
            $sortArrow = function ($key) use ($sort) {
                return $sort === $key ? ' ↓' : '';
            };
            $maxViews = ! empty($products) ? max(1, max(array_map(fn ($p) => (int) ($p['views'] ?? 0), $products))) : 1;
        @endphp

        <div class="analytics-kpi-grid">
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Produk dilihat</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M10 7.5C8.61929 7.5 7.5 8.61929 7.5 10C7.5 11.3807 8.61929 12.5 10 12.5C11.3807 12.5 12.5 11.3807 12.5 10C12.5 8.61929 11.3807 7.5 10 7.5Z" stroke="currentColor" stroke-width="1.6"/><path d="M2.5 10C3.5 6.5 6.5 4 10 4C13.5 4 16.5 6.5 17.5 10C16.5 13.5 13.5 16 10 16C6.5 16 3.5 13.5 2.5 10Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ number_format((int) ($interest['products_viewed'] ?? 0)) }}</span>
                <span class="analytics-kpi-sub">Produk berbeda yang dilihat (ANL-51)</span>
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Total dilihat</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M4 14L16 6M16 6H9M16 6V13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ number_format((int) ($interest['total_views'] ?? 0)) }}</span>
                <span class="analytics-kpi-sub">Total event dilihat (ANL-51)</span>
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Perlu ditinjau</span>
                    <span class="analytics-kpi-icon is-down" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 6.5V10.5M10 13.5H10.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ count($highlights) }}</span>
                <span class="analytics-kpi-sub">Dilihat ≥ 20, rasio beli ≤ 5%, batas atas Wilson ≤ 10% (ANL-53)</span>
            </div>
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Pembanding</span>
                    <span class="analytics-kpi-icon is-up" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5L16.5 6.25V13.75L10 17.5L3.5 13.75V6.25L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3.5 6.25L10 10L16.5 6.25M10 10V17.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ count($comparators) }}</span>
                <span class="analytics-kpi-sub">Rasio titik ≥ 10% dan pembelian ≥ 10, maks 3 (ANL-56)</span>
            </div>
        </div>

        <div class="analytics-split-grid">
            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Perlu ditinjau</h2>
                        <p class="analytics-card-sub">Banyak dilihat tapi jarang dibeli — urut selisih keranjang − pembelian terbesar</p>
                    </div>
                    <span class="analytics-trend-label analytics-trend-label--turun">{{ count($highlights) }} produk</span>
                </div>
                @if (! empty($highlights))
                    <ol class="analytics-list analytics-rank-list">
                        @foreach ($highlights as $product)
                            <li class="analytics-rank-item">
                                <div class="analytics-rank-row">
                                    <span class="analytics-rank-name">{{ $product['name'] }}</span>
                                    <span class="analytics-rank-meta">{{ number_format((int) ($product['views'] ?? 0)) }} dilihat &bull; {{ number_format((int) ($product['purchases'] ?? 0)) }} dibeli &bull; rasio {{ $fmtPct($product['ratio'] ?? null) ?? ($product['ratio_text'] ?? '—') }}</span>
                                </div>
                                <div class="analytics-bar-track" aria-hidden="true">
                                    <span class="analytics-bar-fill is-down" style="width: {{ max(6, min(100, round(((int) ($product['cart_minus_purchase'] ?? 0) / max(1, (int) ($product['views'] ?? 1))) * 100))) }}%"></span>
                                </div>
                                <div class="analytics-rank-foot">
                                    <span class="analytics-note">Keranjang − pembelian: <strong>{{ number_format((int) ($product['cart_minus_purchase'] ?? 0)) }}</strong> &bull; Interval Wilson 95%: {{ $fmtInterval($product['interval'] ?? null) }}</span>
                                    <a class="btn-ghost-sm analytics-history-pill" href="{{ route('admin.aktivitas.riwayat', ['produk' => $product['product_id']]) }}">Lihat riwayat &rarr;</a>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="analytics-note">Tidak ada produk yang perlu ditinjau pada periode ini.</p>
                @endif
            </div>

            <div class="card admin-panel">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title">Pembanding</h2>
                        <p class="analytics-card-sub">Produk dengan minat beli tinggi sebagai pembanding sorotan</p>
                    </div>
                    <span class="analytics-trend-label analytics-trend-label--naik">{{ count($comparators) }} produk</span>
                </div>
                @if (! empty($comparators))
                    <ol class="analytics-list analytics-rank-list">
                        @foreach ($comparators as $product)
                            <li class="analytics-rank-item">
                                <div class="analytics-rank-row">
                                    <span class="analytics-rank-name">{{ $product['name'] }}</span>
                                    <span class="analytics-rank-meta">rasio {{ $fmtPct($product['ratio'] ?? null) ?? ($product['ratio_text'] ?? '—') }} &bull; {{ number_format((int) ($product['purchases'] ?? 0)) }} dibeli</span>
                                </div>
                                <div class="analytics-bar-track" aria-hidden="true">
                                    <span class="analytics-bar-fill is-up" style="width: {{ max(6, min(100, round(((float) ($product['ratio'] ?? 0)) * 100))) }}%"></span>
                                </div>
                                <div class="analytics-rank-foot">
                                    <span class="analytics-note">{{ number_format((int) ($product['views'] ?? 0)) }} dilihat &bull; Interval Wilson 95%: {{ $fmtInterval($product['interval'] ?? null) }}</span>
                                    <a class="btn-ghost-sm analytics-history-pill" href="{{ route('admin.aktivitas.riwayat', ['produk' => $product['product_id']]) }}">Lihat riwayat &rarr;</a>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="analytics-note">Tidak ada pembanding pada periode ini.</p>
                @endif
            </div>
        </div>

        <div class="card admin-panel">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title">Minat per produk</h2>
                    <p class="analytics-card-sub">Klik judul kolom untuk mengurutkan &bull; Rasio beli = pembelian / dilihat</p>
                </div>
                <div class="analytics-sort-pills" role="group" aria-label="Urutkan tabel minat">
                    <a class="btn-ghost-sm {{ $sort === 'dilihat' ? 'is-active' : '' }}" href="{{ $sortUrl('dilihat') }}">Urut: Dilihat</a>
                    <a class="btn-ghost-sm {{ $sort === 'pembelian' ? 'is-active' : '' }}" href="{{ $sortUrl('pembelian') }}">Urut: Pembelian</a>
                    <a class="btn-ghost-sm {{ $sort === 'rasio' ? 'is-active' : '' }}" href="{{ $sortUrl('rasio') }}">Urut: Rasio beli</a>
                    <a class="btn-ghost-sm {{ $sort === 'nama' ? 'is-active' : '' }}" href="{{ $sortUrl('nama') }}">Urut: Nama</a>
                </div>
            </div>
            @if (! empty($products))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><a class="admin-cell-link" href="{{ $sortUrl('nama') }}">Produk{{ $sortArrow('nama') }}</a></th>
                                <th><a class="admin-cell-link" href="{{ $sortUrl('dilihat') }}">Dilihat{{ $sortArrow('dilihat') }}</a></th>
                                <th>Keranjang</th>
                                <th>Booking</th>
                                <th><a class="admin-cell-link" href="{{ $sortUrl('pembelian') }}">Pembelian{{ $sortArrow('pembelian') }}</a></th>
                                <th><a class="admin-cell-link" href="{{ $sortUrl('rasio') }}">Rasio beli{{ $sortArrow('rasio') }}</a></th>
                                <th>Interval Wilson 95%</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                @php
                                    $statusText = (string) ($product['status'] ?? '—');
                                    $statusClass = match ($statusText) {
                                        'Perlu ditinjau' => 'analytics-trend-label--turun',
                                        'Tidak disorot' => 'analytics-trend-label--naik',
                                        default => '',
                                    };
                                    $ratioVal = $product['ratio'] ?? null;
                                    $ratioPct = $ratioVal !== null ? round((float) $ratioVal * 100) : 0;
                                    $viewVal = (int) ($product['views'] ?? 0);
                                    $viewPct = round(($viewVal / $maxViews) * 100);
                                @endphp
                                <tr>
                                    <td class="admin-cell-primary">
                                        {{ $product['name'] }}
                                        @if ($product['anomaly'] ?? false)
                                            <span class="analytics-note">({{ $product['note'] ?? 'Catatan data' }})</span>
                                        @endif
                                    </td>
                                    <td class="admin-cell-nowrap">
                                        <div class="analytics-table-bar-cell">
                                            <span>{{ number_format($viewVal) }}</span>
                                            <span class="analytics-inline-bar" aria-hidden="true">
                                                <span class="analytics-inline-bar-fill" style="width: {{ max(6, $viewPct) }}%"></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="admin-cell-nowrap">{{ number_format((int) ($product['add_to_carts'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap">{{ number_format((int) ($product['bookings'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap admin-cell-strong">{{ number_format((int) ($product['purchases'] ?? 0)) }}</td>
                                    <td class="admin-cell-nowrap">
                                        <div class="analytics-table-bar-cell">
                                            <span>{{ $fmtPct($ratioVal) ?? ($product['ratio_text'] ?? '—') }}</span>
                                            @if ($ratioVal !== null)
                                                <span class="analytics-inline-bar" aria-hidden="true">
                                                    <span class="analytics-inline-bar-fill {{ ($product['needs_review'] ?? false) ? 'is-down' : 'is-up' }}" style="width: {{ max(6, min(100, $ratioPct)) }}%"></span>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="admin-cell-nowrap">{{ $fmtInterval($product['interval'] ?? null) }}</td>
                                    <td><span class="analytics-trend-label {{ $statusClass }}">{{ $statusText }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="analytics-note">Tidak ada produk untuk dianalisis pada periode dan filter ini.</p>
            @endif
        </div>

        @if (! empty($interest['warnings']))
            <div class="card admin-panel analytics-warnings">
                <h2 class="admin-section-title">Catatan data</h2>
                <ul>
                    @foreach ($interest['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection
