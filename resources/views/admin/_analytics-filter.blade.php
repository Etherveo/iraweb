{{-- M5 ANL-01: filter periode bersama halaman analitik (pilihan cepat +
rentang kustom, GET sehingga periode bisa dibagikan lewat URL). --}}
@php
    $tz = config('ira.reporting_timezone', 'Asia/Makassar');
    $today = \Illuminate\Support\Carbon::now($tz);
    $quick = [
        'Hari ini' => [$today->toDateString(), $today->toDateString()],
        '7 hari' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
        '30 hari' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
        'Bulan ini' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
        'Bulan lalu' => [
            $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        ],
    ];
    // M6 ANL-54: pilihan cepat mempertahankan filter kategori bila ada
    // (hanya halaman Aktivitas & Minat yang mengirim $categories).
    $keepKategori = request()->filled('kategori') ? ['kategori' => request('kategori')] : [];
@endphp
<div class="card admin-panel analytics-filter">
    <div class="analytics-filter-top">
        <div class="analytics-quick" role="group" aria-label="Pilihan cepat periode">
            @foreach ($quick as $label => [$d, $s])
                <a class="btn-secondary analytics-quick-pill" href="{{ route($filterRoute, array_merge(['dari' => $d, 'sampai' => $s], $keepKategori)) }}" @if (($dari ?? null) === $d && ($sampai ?? null) === $s) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </div>
        @if (! empty($dari) && ! empty($sampai))
            <div class="analytics-period-badge">
                <svg class="analytics-period-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <rect x="3" y="4.5" width="14" height="12.5" rx="2.5" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M6.5 2.75V5.75M13.5 2.75V5.75M3 8.25H17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>{{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} &bull; WITA</span>
            </div>
        @endif
    </div>
    <form method="GET" action="{{ route($filterRoute) }}" class="admin-filters analytics-filter-form">
        <div class="admin-filter-group">
            <label for="dari" class="admin-label">Dari tanggal</label>
            <input class="input" type="date" id="dari" name="dari" value="{{ old('dari', $dari ?? '') }}" max="{{ $today->toDateString() }}" @error('dari') aria-invalid="true" aria-describedby="error-dari" @enderror>
            @error('dari') <span class="field-error" id="error-dari">{{ $message }}</span> @enderror
        </div>
        <div class="admin-filter-group">
            <label for="sampai" class="admin-label">Sampai tanggal</label>
            <input class="input" type="date" id="sampai" name="sampai" value="{{ old('sampai', $sampai ?? '') }}" max="{{ $today->toDateString() }}" @error('sampai') aria-invalid="true" aria-describedby="error-sampai" @enderror>
            @error('sampai') <span class="field-error" id="error-sampai">{{ $message }}</span> @enderror
        </div>
        @if (isset($categories))
            <div class="admin-filter-group">
                <label for="kategori" class="admin-label">Kategori</label>
                <select class="input" id="kategori" name="kategori">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category['id'] }}" @selected((string) request('kategori', '') === (string) $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="admin-filter-actions">
            <button class="btn-primary" type="submit">Tampilkan</button>
            @if (request()->query('dari') || request()->query('sampai') || request()->query('kategori'))
                <a class="btn-secondary" href="{{ route($filterRoute) }}">Reset</a>
            @endif
        </div>
    </form>
</div>
