{{--
    Campuran status akhir (SIM-23, harus 100%) + rasio funnel (SIM-12).
--}}
<div class="developer-field">
    <span class="developer-label">Campuran status akhir (total harus 100%)</span>
    <div class="developer-field-grid">
        <div class="developer-field">
            <label class="developer-label" for="status_mix_selesai">Selesai (%)</label>
            <input class="input" type="number" id="status_mix_selesai" name="status_mix[selesai]" value="{{ old('status_mix.selesai', $statusMix['selesai'] ?? 80) }}" min="0" max="100" step="1"
                   @error('status_mix') aria-invalid="true" @enderror>
        </div>
        <div class="developer-field">
            <label class="developer-label" for="status_mix_dibatalkan">Dibatalkan (%)</label>
            <input class="input" type="number" id="status_mix_dibatalkan" name="status_mix[dibatalkan]" value="{{ old('status_mix.dibatalkan', $statusMix['dibatalkan'] ?? 10) }}" min="0" max="100" step="1"
                   @error('status_mix') aria-invalid="true" @enderror>
        </div>
        <div class="developer-field">
            <label class="developer-label" for="status_mix_kedaluwarsa">Kedaluwarsa (%)</label>
            <input class="input" type="number" id="status_mix_kedaluwarsa" name="status_mix[kedaluwarsa]" value="{{ old('status_mix.kedaluwarsa', $statusMix['kedaluwarsa'] ?? 10) }}" min="0" max="100" step="1"
                   @error('status_mix') aria-invalid="true" @enderror>
        </div>
    </div>
    @error('status_mix')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>

<div class="developer-field-grid">
    <div class="developer-field">
        <label class="developer-label" for="funnel_view_to_cart">Rasio view → cart (%)</label>
        <input class="input" type="number" id="funnel_view_to_cart" name="funnel_view_to_cart" value="{{ old('funnel_view_to_cart', $funnelViewToCart) }}" min="1" max="100" step="1"
               @error('funnel_view_to_cart') aria-invalid="true" @enderror>
        @error('funnel_view_to_cart')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
    <div class="developer-field">
        <label class="developer-label" for="funnel_cart_to_booking">Rasio cart → booking (%)</label>
        <input class="input" type="number" id="funnel_cart_to_booking" name="funnel_cart_to_booking" value="{{ old('funnel_cart_to_booking', $funnelCartToBooking) }}" min="1" max="100" step="1"
               @error('funnel_cart_to_booking') aria-invalid="true" @enderror>
        @error('funnel_cart_to_booking')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
</div>
