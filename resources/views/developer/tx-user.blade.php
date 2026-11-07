@extends('layouts.app')

@section('content')
    <div class="developer-page developer-page-narrow">
        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Aksi 2</p>
                <h1 class="developer-title">Transaksi per User</h1>
                <p class="developer-subtitle">Aksi 2 (SIM-09..15): pilih akun simulasi, lalu buat transaksi backdate untuk tiap akun — jumlah transaksi per akun sama persis. Pratinjau wajib sebelum eksekusi (SIM-25).</p>
            </div>
        </header>

        <div class="card developer-panel">
            <form class="developer-form" method="POST" action="{{ route('developer.tx-user.preview') }}">
                @csrf

                <div class="developer-form-section">
                    @include('developer.partials._account-picker', ['accounts' => $accounts])

                    <div class="developer-field">
                        <label class="developer-label" for="transactions_per_account">Transaksi per akun</label>
                        <input class="input" type="number" id="transactions_per_account" name="transactions_per_account" value="{{ old('transactions_per_account', 1) }}" min="1" step="1" required
                               @error('transactions_per_account') aria-invalid="true" aria-describedby="transactions_per_account-error" @enderror>
                        <p class="developer-hint">Total transaksi = jumlah akun × transaksi per akun, maksimal {{ $maxTransactions }} per request (SIM-17).</p>
                        @error('transactions_per_account')
                            <p class="field-error" id="transactions_per_account-error">{{ $message }}</p>
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
                    <p class="developer-hint">Tanpa produk ganda dalam satu pesanan; kuantitas 1–99 (SIM-11).</p>
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
