@extends('layouts.app')

@section('content')
    <div class="developer-page developer-page-narrow">
        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Aksi 3</p>
                <h1 class="developer-title">Transaksi per Periode</h1>
                <p class="developer-subtitle">Aksi 3 (SIM-16..24): tentukan periode dan jumlah transaksi, lalu pilih cakupan user. Pratinjau wajib sebelum eksekusi (SIM-25).</p>
            </div>
        </header>

        <div class="card developer-panel">
            <form class="developer-form" method="POST" action="{{ route('developer.tx-period.preview') }}">
                @csrf

                <div class="developer-form-section">
                    <div class="developer-field" data-user-scope>
                        <span class="developer-label">Cakupan user (SIM-18/19)</span>
                        <div role="radiogroup" aria-label="Cakupan user">
                            <label class="developer-check-label">
                                <input type="radio" name="user_scope" value="all" data-user-scope-option="all" {{ old('user_scope', $defaultScope) === 'all' ? 'checked' : '' }}>
                                <span>Semua akun simulasi</span>
                            </label>
                            <label class="developer-check-label">
                                <input type="radio" name="user_scope" value="one" data-user-scope-option="one" {{ old('user_scope', $defaultScope) === 'one' ? 'checked' : '' }}>
                                <span>Satu user (pilih tepat 1 akun)</span>
                            </label>
                            <label class="developer-check-label">
                                <input type="radio" name="user_scope" value="several" data-user-scope-option="several" {{ old('user_scope', $defaultScope) === 'several' ? 'checked' : '' }}>
                                <span>Beberapa user (pilih ≥ 1 akun)</span>
                            </label>
                            <label class="developer-check-label">
                                <input type="radio" name="user_scope" value="random" data-user-scope-option="random" {{ old('user_scope', $defaultScope) === 'random' ? 'checked' : '' }}>
                                <span>Acak (k user, berbenih)</span>
                            </label>
                        </div>
                        <div class="developer-field" data-user-scope-random {{ old('user_scope', $defaultScope) === 'random' ? '' : 'hidden' }}>
                            <label class="developer-label" for="random_k">Jumlah user acak (k)</label>
                            <input class="input" type="number" id="random_k" name="random_k" value="{{ old('random_k', 5) }}" min="1" step="1"
                                   @error('random_k') aria-invalid="true" @enderror>
                            @error('random_k')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div data-user-scope-picker {{ in_array(old('user_scope', $defaultScope), ['one', 'several'], true) ? '' : 'hidden' }}>
                            @include('developer.partials._account-picker', ['accounts' => $accounts])
                        </div>
                        @error('user_scope')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="developer-field">
                        <label class="developer-label" for="transaction_count">Jumlah transaksi</label>
                        <input class="input" type="number" id="transaction_count" name="transaction_count" value="{{ old('transaction_count', 10) }}" min="1" max="{{ $maxTransactions }}" step="1" required
                               @error('transaction_count') aria-invalid="true" aria-describedby="transaction_count-error" @enderror>
                        <p class="developer-hint">Maksimal {{ $maxTransactions }} transaksi per request (SIM-17).</p>
                        @error('transaction_count')
                            <p class="field-error" id="transaction_count-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="developer-form-section">
                    @include('developer.partials._period-fields', ['quickPicks' => $quickPicks])
                </div>

                <div class="developer-form-section">
                    <div class="developer-field-grid">
                        <div class="developer-field">
                            <label class="developer-label" for="items_per_tx_min">Item per transaksi (min)</label>
                            <input class="input" type="number" id="items_per_tx_min" name="items_per_tx[min]" value="{{ old('items_per_tx.min', 1) }}" min="1" step="1"
                                   @error('items_per_tx') aria-invalid="true" @enderror>
                        </div>
                        <div class="developer-field">
                            <label class="developer-label" for="items_per_tx_max">Item per transaksi (maks)</label>
                            <input class="input" type="number" id="items_per_tx_max" name="items_per_tx[max]" value="{{ old('items_per_tx.max', 3) }}" min="1" step="1"
                                   @error('items_per_tx') aria-invalid="true" @enderror>
                        </div>
                        <div class="developer-field">
                            <label class="developer-label" for="qty_per_item_min">Kuantitas per item (min)</label>
                            <input class="input" type="number" id="qty_per_item_min" name="qty_per_item[min]" value="{{ old('qty_per_item.min', 1) }}" min="1" step="1"
                                   @error('qty_per_item') aria-invalid="true" @enderror>
                        </div>
                        <div class="developer-field">
                            <label class="developer-label" for="qty_per_item_max">Kuantitas per item (maks)</label>
                            <input class="input" type="number" id="qty_per_item_max" name="qty_per_item[max]" value="{{ old('qty_per_item.max', 99) }}" min="1" max="99" step="1"
                                   @error('qty_per_item') aria-invalid="true" @enderror>
                        </div>
                    </div>
                    @error('items_per_tx')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    @error('qty_per_item')
                        <p class="field-error">{{ $message }}</p>
                    @enderror

                    @include('developer.partials._distribution-fields')
                    @include('developer.partials._product-picker', ['products' => $products])
                </div>

                <div class="developer-form-section">
                    @include('developer.partials._status-mix-fields', ['statusMix' => $statusMix, 'funnelViewToCart' => $funnelViewToCart, 'funnelCartToBooking' => $funnelCartToBooking])
                    @include('developer.partials._seed-fields', ['defaultMode' => $defaultMode, 'defaultSeed' => $defaultSeed])
                </div>

                <div class="developer-form-actions">
                    <button class="btn-primary" type="submit">Lihat pratinjau</button>
                    <a class="btn-secondary" href="{{ route('developer.dashboard') }}">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
