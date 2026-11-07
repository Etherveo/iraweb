{{-- M5 K8: perubahan KPI vs periode sebelumnya. Perbandingan bersifat
deskriptif (tanda signifikansi ditampilkan bila modul menghitungnya);
bila tidak ada data pembanding, tampilkan "Belum dapat dihitung" (K7). --}}
@if (($vs['available'] ?? false) === true && ($vs['change_ratio'] ?? null) !== null)
    @php
        $pct = number_format((float) $vs['change_ratio'] * 100, 1, ',', '.');
        $sign = (float) $vs['change_ratio'] > 0 ? '+' : '';
    @endphp
    <span class="analytics-kpi-sub">{{ $sign }}{{ $pct }}% vs periode sebelumnya
        @if (($vs['significant'] ?? null) === true) · signifikan
        @elseif (($vs['significant'] ?? null) === false) · tidak signifikan
        @endif
    </span>
@else
    <span class="analytics-kpi-sub">{{ $vs['text'] ?? 'Belum dapat dihitung' }}</span>
@endif
