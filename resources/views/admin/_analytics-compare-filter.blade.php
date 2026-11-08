{{-- M6 ANL-41/ANL-46: filter dua periode untuk halaman Perbandingan
(periode A + periode B + pintasan "periode sebelumnya"). GET sehingga
perbandingan bisa dibagikan lewat URL. --}}
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
<div class="card admin-panel analytics-filter">
    <div class="analytics-filter-top">
        <div class="analytics-quick" role="group" aria-label="Pilihan cepat periode A">
            @foreach ($quick as $label => [$d, $s])
                <a class="btn-secondary analytics-quick-pill" href="{{ route('admin.perbandingan', ['dari' => $d, 'sampai' => $s]) }}" @if (($dari ?? null) === $d && ($sampai ?? null) === $s) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <div class="analytics-period-badges">
            @if (! empty($dari) && ! empty($sampai))
                <div class="analytics-period-badge">
                    <span class="analytics-legend-dot" aria-hidden="true"></span>
                    <span>Periode A: {{ \Illuminate\Support\Carbon::parse($dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($sampai)->format('d M Y') }} &bull; WITA</span>
                </div>
            @endif
            @if (! empty($pembanding_dari) && ! empty($pembanding_sampai))
                <div class="analytics-period-badge is-secondary">
                    <span class="analytics-legend-dot is-neutral" aria-hidden="true"></span>
                    <span>Periode B: {{ \Illuminate\Support\Carbon::parse($pembanding_dari)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($pembanding_sampai)->format('d M Y') }} &bull; WITA</span>
                </div>
            @endif
        </div>
    </div>
    <form method="GET" action="{{ route('admin.perbandingan') }}" class="analytics-compare-form">
        <div class="analytics-compare-periods">
            <div class="analytics-compare-box">
                <div class="analytics-compare-box-head">
                    <span class="analytics-legend-dot" aria-hidden="true"></span>
                    <span class="analytics-compare-box-title">Periode A (Utama)</span>
                </div>
                <div class="analytics-compare-box-fields">
                    <div class="admin-filter-group">
                        <label for="dari" class="admin-label">Periode A — dari tanggal</label>
                        <input class="input" type="date" id="dari" name="dari" value="{{ old('dari', $dari ?? '') }}" max="{{ $today->toDateString() }}" @error('dari') aria-invalid="true" aria-describedby="error-dari" @enderror>
                        @error('dari') <span class="field-error" id="error-dari">{{ $message }}</span> @enderror
                    </div>
                    <div class="admin-filter-group">
                        <label for="sampai" class="admin-label">Periode A — sampai tanggal</label>
                        <input class="input" type="date" id="sampai" name="sampai" value="{{ old('sampai', $sampai ?? '') }}" max="{{ $today->toDateString() }}" @error('sampai') aria-invalid="true" aria-describedby="error-sampai" @enderror>
                        @error('sampai') <span class="field-error" id="error-sampai">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="analytics-compare-box is-secondary">
                <div class="analytics-compare-box-head">
                    <span class="analytics-legend-dot is-neutral" aria-hidden="true"></span>
                    <span class="analytics-compare-box-title">Periode B (Pembanding)</span>
                </div>
                <div class="analytics-compare-box-fields">
                    <div class="admin-filter-group">
                        <label for="pembanding_dari" class="admin-label">Periode B — dari tanggal</label>
                        <input class="input" type="date" id="pembanding_dari" name="pembanding_dari" value="{{ old('pembanding_dari', $pembanding_dari ?? '') }}" max="{{ $today->toDateString() }}" @error('pembanding_dari') aria-invalid="true" aria-describedby="error-pembanding-dari" @enderror>
                        @error('pembanding_dari') <span class="field-error" id="error-pembanding-dari">{{ $message }}</span> @enderror
                    </div>
                    <div class="admin-filter-group">
                        <label for="pembanding_sampai" class="admin-label">Periode B — sampai tanggal</label>
                        <input class="input" type="date" id="pembanding_sampai" name="pembanding_sampai" value="{{ old('pembanding_sampai', $pembanding_sampai ?? '') }}" max="{{ $today->toDateString() }}" @error('pembanding_sampai') aria-invalid="true" aria-describedby="error-pembanding-sampai" @enderror>
                        @error('pembanding_sampai') <span class="field-error" id="error-pembanding-sampai">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-filter-actions analytics-compare-actions">
            <button class="btn-primary" type="submit">Bandingkan</button>
            @if (! empty($dari) && ! empty($sampai))
                <a class="btn-secondary" href="{{ route('admin.perbandingan', ['dari' => $dari, 'sampai' => $sampai, 'pembanding' => 'sebelumnya']) }}">Isi B dengan periode sebelumnya</a>
            @endif
            @if (request()->query('dari') || request()->query('sampai') || request()->query('pembanding_dari') || request()->query('pembanding_sampai'))
                <a class="btn-secondary" href="{{ route('admin.perbandingan') }}">Reset</a>
            @endif
        </div>
    </form>
</div>
