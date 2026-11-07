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
@endphp
<div class="card analytics-filter">
    <div class="analytics-quick" role="group" aria-label="Pilihan cepat periode">
        @foreach ($quick as $label => [$d, $s])
            <a class="btn-secondary" href="{{ route($filterRoute, ['dari' => $d, 'sampai' => $s]) }}" @if (($dari ?? null) === $d && ($sampai ?? null) === $s) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    <form method="GET" action="{{ route($filterRoute) }}" class="admin-filters">
        <div>
            <label for="dari">Dari tanggal</label>
            <input type="date" id="dari" name="dari" value="{{ old('dari', $dari ?? '') }}" max="{{ $today->toDateString() }}" @error('dari') aria-invalid="true" aria-describedby="error-dari" @enderror>
            @error('dari') <span class="field-error" id="error-dari">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="sampai">Sampai tanggal</label>
            <input type="date" id="sampai" name="sampai" value="{{ old('sampai', $sampai ?? '') }}" max="{{ $today->toDateString() }}" @error('sampai') aria-invalid="true" aria-describedby="error-sampai" @enderror>
            @error('sampai') <span class="field-error" id="error-sampai">{{ $message }}</span> @enderror
        </div>
        <div>
            <button class="btn-primary" type="submit">Tampilkan</button>
            @if (request()->query('dari') || request()->query('sampai'))
                <a class="btn-secondary" href="{{ route($filterRoute) }}">Reset</a>
            @endif
        </div>
    </form>
</div>
