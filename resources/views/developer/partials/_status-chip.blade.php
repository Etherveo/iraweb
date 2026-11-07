{{-- Status run (UI.md §11: warna + ikon + label teks, bukan warna saja). --}}
@php
    $chipMap = [
        'running' => ['class' => 'is-running', 'symbol' => '◷', 'label' => 'Berjalan'],
        'berhasil' => ['class' => 'is-berhasil', 'symbol' => '✓', 'label' => 'Berhasil'],
        'gagal' => ['class' => 'is-gagal', 'symbol' => '✕', 'label' => 'Gagal'],
        'direset' => ['class' => 'is-direset', 'symbol' => '↺', 'label' => 'Direset'],
    ];
    $chip = $chipMap[$status] ?? ['class' => 'is-direset', 'symbol' => '?', 'label' => $status];
@endphp
<span class="sim-status {{ $chip['class'] }}"><span aria-hidden="true">{{ $chip['symbol'] }}</span> {{ $chip['label'] }}</span>
