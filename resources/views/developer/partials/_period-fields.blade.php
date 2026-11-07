{{--
    Periode simulasi (SIM-16/28): pilihan cepat + dua input tanggal.
    Tanggal pilihan cepat dihitung server (WITA) agar zona waktu
    browser tidak menggesernya.
--}}
<div class="developer-field" data-period-scope>
    <span class="developer-label">Periode (WITA)</span>
    <div class="developer-form-actions" role="group" aria-label="Pilihan cepat periode">
        <button type="button" class="btn-secondary" data-period-quick data-start="{{ $quickPicks['today'][0] }}" data-end="{{ $quickPicks['today'][1] }}">Hari ini</button>
        <button type="button" class="btn-secondary" data-period-quick data-start="{{ $quickPicks['week'][0] }}" data-end="{{ $quickPicks['week'][1] }}">Minggu ini</button>
        <button type="button" class="btn-secondary" data-period-quick data-start="{{ $quickPicks['month'][0] }}" data-end="{{ $quickPicks['month'][1] }}">Bulan ini</button>
    </div>
    <div class="developer-field-grid">
        <div class="developer-field">
            <label class="developer-label" for="period_start">Tanggal mulai</label>
            <input class="input" type="date" id="period_start" name="period_start" value="{{ old('period_start') }}" required
                   @if ($errors->has('period_start') || $errors->has('period')) aria-invalid="true" @endif>
            @error('period_start')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>
        <div class="developer-field">
            <label class="developer-label" for="period_end">Tanggal akhir</label>
            <input class="input" type="date" id="period_end" name="period_end" value="{{ old('period_end') }}" required
                   @if ($errors->has('period_end') || $errors->has('period')) aria-invalid="true" @endif>
            @error('period_end')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <p class="developer-hint">Mulai ≤ akhir, akhir tidak boleh di masa depan, panjang maksimal 366 hari (SIM-28). Semua stempel transaksi berada di dalam periode ini (SIM-29).</p>
    @error('period')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
