{{--
    Pemilih produk (SIM-11/22). Kosongkan = semua produk aktif.
    Harga yang dipakai adalah harga saat eksekusi (SIM-15).
--}}
<div class="developer-field">
    <span class="developer-label" id="product-picker-label">Produk</span>
    <p class="developer-hint">Kosongkan untuk memakai semua produk aktif ({{ $products->count() }} produk). Hanya produk aktif yang bisa dipilih.</p>
    <div class="developer-account-list" role="group" aria-labelledby="product-picker-label">
        @foreach ($products as $product)
            <label class="developer-check-label">
                <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" {{ in_array($product->id, (array) old('product_ids', [])) ? 'checked' : '' }}>
                <span>
                    <strong>{{ $product->name }}</strong><br>
                    <small>{{ $product->category?->name ?? 'Tanpa kategori' }} · Rp{{ number_format($product->price_idr, 0, ',', '.') }}</small>
                </span>
            </label>
        @endforeach
    </div>
    @error('product_ids')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
