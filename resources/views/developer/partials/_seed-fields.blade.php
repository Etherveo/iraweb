{{--
    Mode + seed biodata/rencana (U-02). Default deterministik dengan
    seed 20261101; mode random memakai seed acak bila dikosongkan.
--}}
<div class="developer-field-grid">
    <div class="developer-field">
        <span class="developer-label">Mode</span>
        <div role="radiogroup" aria-label="Mode">
            <label class="developer-check-label">
                <input type="radio" name="mode" value="deterministic" {{ old('mode', $defaultMode) === 'deterministic' ? 'checked' : '' }}>
                <span>Deterministik (bisa diulang)</span>
            </label>
            <label class="developer-check-label">
                <input type="radio" name="mode" value="random" {{ old('mode', $defaultMode) === 'random' ? 'checked' : '' }}>
                <span>Acak</span>
            </label>
        </div>
        @error('mode')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
    <div class="developer-field">
        <label class="developer-label" for="seed">Seed</label>
        <input class="input" type="number" id="seed" name="seed" value="{{ old('seed', $defaultSeed) }}" step="1">
        <p class="developer-hint">Seed yang sama + parameter yang sama = rencana yang sama (SIM-05). Kosongkan untuk seed acak baru (mode random) atau seed default (mode deterministik).</p>
        @error('seed')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
</div>
