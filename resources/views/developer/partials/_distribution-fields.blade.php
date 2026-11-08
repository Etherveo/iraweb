{{--
    Distribusi waktu/user/produk (§9.4, U-08): default acak.
    Distribusi 'satu titik' membutuhkan tanggal+jam (WITA).
--}}
<div class="developer-field">
    <span class="developer-label">Distribusi waktu</span>
    <div role="radiogroup" aria-label="Distribusi waktu">
        @foreach (['random' => 'Acak', 'even' => 'Merata', 'single' => 'Satu titik', 'rising' => 'Menaik', 'falling' => 'Menurun'] as $value => $label)
            <label class="developer-check-label">
                <input type="radio" name="time_distribution" value="{{ $value }}" data-time-distribution="{{ $value }}" {{ old('time_distribution', 'random') === $value ? 'checked' : '' }}>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    <div class="developer-field" data-single-point-field {{ old('time_distribution') === 'single' ? '' : 'hidden' }}>
        <label class="developer-label" for="single_point">Tanggal + jam (WITA)</label>
        <input class="input" type="datetime-local" id="single_point" name="single_point" value="{{ old('single_point') }}"
               @error('single_point') aria-invalid="true" @enderror>
        <p class="developer-hint">Semua booking terjadi pada titik ini; jeda rantai funnel dikompresi bila titiknya dekat tepi periode (SIM-29).</p>
    </div>
    @error('time_distribution')
        <p class="field-error">{{ $message }}</p>
    @enderror
    @error('single_point')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>

<div class="developer-field-grid">
    <div class="developer-field">
        <span class="developer-label">Distribusi user</span>
        <div role="radiogroup" aria-label="Distribusi user">
            @foreach (['random' => 'Acak', 'even' => 'Merata', 'weighted' => 'Berbobot'] as $value => $label)
                <label class="developer-check-label">
                    <input type="radio" name="user_distribution" value="{{ $value }}" {{ old('user_distribution', 'random') === $value ? 'checked' : '' }}>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('user_distribution')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
    <div class="developer-field">
        <span class="developer-label">Distribusi produk</span>
        <div role="radiogroup" aria-label="Distribusi produk">
            @foreach (['random' => 'Acak', 'even' => 'Merata', 'weighted' => 'Berbobot'] as $value => $label)
                <label class="developer-check-label">
                    <input type="radio" name="product_distribution" value="{{ $value }}" {{ old('product_distribution', 'random') === $value ? 'checked' : '' }}>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('product_distribution')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
</div>
