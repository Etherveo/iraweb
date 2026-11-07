@extends('layouts.app')

@section('content')
    <div class="developer-page developer-page-narrow">
        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Aksi 4</p>
                <h1 class="developer-title">Funnel Eksplisit</h1>
                <p class="developer-subtitle">Aksi 4 (SIM-31): tentukan jumlah tiap tahap funnel secara eksplisit. Setiap booking adalah satu pesanan nyata (1 item, kuantitas 1); purchase adalah booking yang dibayar, sisanya dibagi dibatalkan/kedaluwarsa. Pratinjau wajib sebelum eksekusi (SIM-25).</p>
            </div>
        </header>

        <div class="card developer-panel">
            <form class="developer-form" method="POST" action="{{ route('developer.funnel.preview') }}">
                @csrf

                <div class="developer-form-section">
                    @include('developer.partials._account-picker', ['accounts' => $accounts])
                </div>

                <div class="developer-form-section">
                    @include('developer.partials._period-fields', ['quickPicks' => $quickPicks])
                </div>

                <div class="developer-form-section">
                    <div class="developer-field">
                        <span class="developer-label">Jumlah funnel</span>
                        <p class="developer-hint">Harus menyusut: view ≥ add_to_cart ≥ booking ≥ purchase ≥ 0. Total event (view + add_to_cart + 2 × booking) maksimal {{ $maxEvents }} per request.</p>
                        <div class="developer-field-grid">
                            <div class="developer-field">
                                <label class="developer-label" for="view_count">View</label>
                                <input class="input" type="number" id="view_count" name="view_count" value="{{ old('view_count', 100) }}" min="0" step="1" required
                                       @error('view_count') aria-invalid="true" @enderror>
                                @error('view_count')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="developer-field">
                                <label class="developer-label" for="add_to_cart_count">Add to cart</label>
                                <input class="input" type="number" id="add_to_cart_count" name="add_to_cart_count" value="{{ old('add_to_cart_count', 40) }}" min="0" step="1" required
                                       @error('add_to_cart_count') aria-invalid="true" @enderror>
                                @error('add_to_cart_count')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="developer-field">
                                <label class="developer-label" for="booking_count">Booking</label>
                                <input class="input" type="number" id="booking_count" name="booking_count" value="{{ old('booking_count', 24) }}" min="0" step="1" required
                                       @error('booking_count') aria-invalid="true" @enderror>
                                @error('booking_count')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="developer-field">
                                <label class="developer-label" for="purchase_count">Purchase</label>
                                <input class="input" type="number" id="purchase_count" name="purchase_count" value="{{ old('purchase_count', 19) }}" min="0" step="1" required
                                       @error('purchase_count') aria-invalid="true" @enderror>
                                @error('purchase_count')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    @include('developer.partials._distribution-fields')
                    @include('developer.partials._product-picker', ['products' => $products])
                </div>

                <div class="developer-form-section">
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
