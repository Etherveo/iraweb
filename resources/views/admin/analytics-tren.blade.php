{{-- M5 FR-02 Tren (K1/K2/K3/K13): tren per produk atas metrik units.
Label dari modul ("Naik"/"Turun"/"Tidak ada tren jelas") — tidak pernah
"stabil" (K3). Periode <8 minggu penuh → "Data belum cukup" (K13). --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <div class="admin-page-head">
        <div>
            <p class="admin-eyebrow">Analitik · Tren</p>
            <h1>Tren Produk</h1>
            <p class="admin-page-sub">Arah penjualan per produk, {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} (WITA). Metrik: unit terjual per minggu.</p>
        </div>
    </div>

    @include('admin._analytics-filter', ['filterRoute' => 'admin.tren'])

    @if ($unavailable)
        @include('admin._analytics-unavailable')
    @else
        @if (($trend['weeks_full'] ?? 0) < 8)
            <div class="admin-info-box" role="alert">
                <p><strong>Data belum cukup.</strong> Periode ini hanya memuat {{ $trend['weeks_full'] ?? 0 }} minggu penuh — tren membutuhkan minimal 8 minggu penuh. Perpanjang periode agar tren dapat dihitung.</p>
            </div>
        @endif

        <div class="card">
            <h2>Tren per produk</h2>
            @if (! empty($trend['products']))
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Produk</th><th>Tren</th><th>Minggu</th><th>Kemiringan (β)</th><th>r</th><th>Perubahan</th><th>Rata-rata / minggu</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($trend['products'] as $product)
                                <tr>
                                    <td>{{ $product['name'] }}</td>
                                    <td><span class="analytics-trend-label analytics-trend-label--{{ $product['label_code'] }}">{{ $product['label'] }}</span>@if (! empty($product['strength'])) <span class="analytics-note">({{ $product['strength'] }})</span>@endif</td>
                                    <td>{{ $product['n'] }}</td>
                                    <td>{{ ($product['beta'] ?? null) !== null ? number_format((float) $product['beta'], 2, ',', '.') : '—' }}</td>
                                    <td>{{ ($product['r'] ?? null) !== null ? number_format((float) $product['r'], 2, ',', '.') : '—' }}</td>
                                    <td>{{ ($product['change_abs'] ?? null) !== null ? number_format((float) $product['change_abs'], 1, ',', '.') : '—' }}</td>
                                    <td>{{ ($product['mean'] ?? null) !== null ? number_format((float) $product['mean'], 1, ',', '.') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="analytics-note">Tidak ada produk untuk dianalisis pada periode ini.</p>
            @endif
        </div>

        @if (! empty($trend['ranking_rising']))
            <div class="card">
                <h2>Naik</h2>
                <ol class="analytics-list">
                    @foreach ($trend['ranking_rising'] as $product)
                        <li><span>{{ $product['name'] }}</span><span>r {{ number_format((float) ($product['r'] ?? 0), 2, ',', '.') }} · {{ number_format((float) ($product['change_abs'] ?? 0), 1, ',', '.') }} unit</span></li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if (! empty($trend['ranking_falling']))
            <div class="card">
                <h2>Turun</h2>
                <ol class="analytics-list">
                    @foreach ($trend['ranking_falling'] as $product)
                        <li><span>{{ $product['name'] }}</span><span>r {{ number_format((float) ($product['r'] ?? 0), 2, ',', '.') }} · {{ number_format((float) ($product['change_abs'] ?? 0), 1, ',', '.') }} unit</span></li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if (! empty($trend['warnings']))
            <div class="card analytics-warnings">
                <h2>Catatan data</h2>
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
