{{-- M5 K8: perubahan KPI vs periode sebelumnya. Perbandingan bersifat
deskriptif (tanda signifikansi ditampilkan bila modul menghitungnya);
bila tidak ada data pembanding, tampilkan "Belum dapat dihitung" (K7). --}}
@if (($vs['available'] ?? false) === true && ($vs['change_ratio'] ?? null) !== null)
    @php
        $ratio = (float) $vs['change_ratio'];
        $pct = number_format($ratio * 100, 1, ',', '.');
        $sign = $ratio > 0 ? '+' : '';
        $dirClass = $ratio > 0 ? 'is-up' : ($ratio < 0 ? 'is-down' : 'is-flat');
    @endphp
    <span class="analytics-kpi-sub analytics-kpi-change {{ $dirClass }}">
        <span class="analytics-kpi-change-main">
            @if ($ratio > 0)
                <svg class="analytics-kpi-trend-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 11.5L12.5 4.5M12.5 4.5H6.5M12.5 4.5V10.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @elseif ($ratio < 0)
                <svg class="analytics-kpi-trend-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 4.5L12.5 11.5M12.5 11.5H6.5M12.5 11.5V5.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @else
                <svg class="analytics-kpi-trend-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8H13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
            @endif
            <span>{{ $sign }}{{ $pct }}% vs periode sebelumnya</span>
        </span>
        @if (($vs['significant'] ?? null) === true)
            <span class="analytics-sig-badge is-sig">· signifikan</span>
        @elseif (($vs['significant'] ?? null) === false)
            <span class="analytics-sig-badge is-insig">· tidak signifikan</span>
        @endif
    </span>
@else
    <span class="analytics-kpi-sub analytics-kpi-change is-empty">{{ $vs['text'] ?? 'Belum dapat dihitung' }}</span>
@endif
