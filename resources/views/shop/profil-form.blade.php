@extends('layouts.app')

@section('content')
    <div class="admin-page admin-page-narrow">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('profil.show') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke profil</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Akun Pembeli</p>
                <h1 class="admin-title">Ubah Profil &amp; Alamat</h1>
                <p class="admin-subtitle">Perbarui informasi biodata dan alamat Anda.</p>
            </div>
        </header>

        <div class="buyer-main-stack">
            <section class="card admin-panel" aria-labelledby="form-profil-heading">
                <h2 class="admin-section-title" id="form-profil-heading">Data Profil</h2>

                <form method="POST" action="{{ route('profil.update') }}" class="admin-form">
                    @csrf
                    @method('PUT')

                    <div class="admin-field">
                        <label for="email_readonly" class="admin-label">Email (tidak dapat diubah)</label>
                        <input class="input" id="email_readonly" type="email" value="{{ $user->email }}" disabled readonly>
                        <p class="admin-hint">Alamat email digunakan sebagai identitas masuk dan tidak dapat diganti.</p>
                    </div>

                    <div class="admin-field">
                        <label for="name" class="admin-label">Nama lengkap (2–100 karakter)</label>
                        <input class="input" id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                               @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        @error('name')<p class="alert-error field-error" id="name-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="admin-field-grid">
                        <div class="admin-field">
                            <label for="username" class="admin-label">Username (opsional, maks. 50 karakter)</label>
                            <input class="input" id="username" name="username" type="text" maxlength="50" value="{{ old('username', $user->username) }}"
                                   @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                            @error('username')<p class="alert-error field-error" id="username-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="admin-field">
                            <label for="phone" class="admin-label">Nomor telepon (opsional, maks. 20 karakter)</label>
                            <input class="input" id="phone" name="phone" type="text" maxlength="20" value="{{ old('phone', $user->phone) }}"
                                   @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone')<p class="alert-error field-error" id="phone-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="admin-field-grid">
                        <div class="admin-field">
                            <label for="gender" class="admin-label">Jenis kelamin (opsional)</label>
                            @php($selectedGender = old('gender', $user->gender))
                            <select class="input" id="gender" name="gender"
                                    @error('gender') aria-invalid="true" aria-describedby="gender-error" @enderror>
                                <option value="">— Pilih jenis kelamin —</option>
                                <option value="laki-laki" @selected($selectedGender === 'laki-laki')>Laki-laki</option>
                                <option value="perempuan" @selected($selectedGender === 'perempuan')>Perempuan</option>
                            </select>
                            @error('gender')<p class="alert-error field-error" id="gender-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="admin-field">
                            <label for="birthdate" class="admin-label">Tanggal lahir (opsional)</label>
                            <input class="input" id="birthdate" name="birthdate" type="date"
                                   max="{{ now()->toDateString() }}"
                                   value="{{ old('birthdate', $user->birthdate?->format('Y-m-d')) }}"
                                   @error('birthdate') aria-invalid="true" aria-describedby="birthdate-error" @enderror>
                            @error('birthdate')<p class="alert-error field-error" id="birthdate-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="admin-form-actions">
                        <button class="btn-primary" type="submit">Simpan Profil</button>
                        <a class="btn-secondary" href="{{ route('profil.show') }}">Batal</a>
                    </div>
                </form>
            </section>

            <section class="card admin-panel" id="form-alamat" aria-labelledby="form-alamat-heading">
                <h2 class="admin-section-title" id="form-alamat-heading">{{ $address ? 'Ubah Alamat Anda' : 'Tambah Alamat Anda' }}</h2>

                <form method="POST" action="{{ route('profil.alamat.update') }}" class="admin-form">
                    @csrf
                    @method('PUT')

                    <div class="admin-field-grid">
                        <div class="admin-field">
                            <label for="recipient_name" class="admin-label">Nama penerima (maks. 100 karakter)</label>
                            <input class="input" id="recipient_name" name="recipient_name" type="text" maxlength="100"
                                   value="{{ old('recipient_name', $address?->recipient_name ?? $user->name) }}" required
                                   @error('recipient_name') aria-invalid="true" aria-describedby="recipient_name-error" @enderror>
                            @error('recipient_name')<p class="alert-error field-error" id="recipient_name-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="admin-field">
                            <label for="recipient_phone" class="admin-label">Telepon penerima (opsional, maks. 20 karakter)</label>
                            <input class="input" id="recipient_phone" name="recipient_phone" type="text" maxlength="20"
                                   value="{{ old('recipient_phone', $address?->recipient_phone ?? $user->phone) }}"
                                   @error('recipient_phone') aria-invalid="true" aria-describedby="recipient_phone-error" @enderror>
                            @error('recipient_phone')<p class="alert-error field-error" id="recipient_phone-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="admin-field-grid">
                        <div class="admin-field">
                            <label for="address_type" class="admin-label">Tipe alamat</label>
                            @php($selectedType = old('address_type', $address?->address_type ?? 'Rumah'))
                            <select class="input" id="address_type" name="address_type" required
                                    @error('address_type') aria-invalid="true" aria-describedby="address_type-error" @enderror>
                                @foreach (['Rumah', 'Kantor', 'Lainnya'] as $typeOption)
                                    <option value="{{ $typeOption }}" @selected($selectedType === $typeOption)>{{ $typeOption }}</option>
                                @endforeach
                            </select>
                            @error('address_type')<p class="alert-error field-error" id="address_type-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="admin-field">
                            <label for="postal_code" class="admin-label">Kode pos (5 digit angka)</label>
                            <input class="input" id="postal_code" name="postal_code" type="text" inputmode="numeric" maxlength="5"
                                   value="{{ old('postal_code', $address?->postal_code) }}" required
                                   @error('postal_code') aria-invalid="true" aria-describedby="postal_code-error" @enderror>
                            @error('postal_code')<p class="alert-error field-error" id="postal_code-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="admin-field-grid">
                        <div class="admin-field">
                            <label for="province" class="admin-label">Provinsi</label>
                            <input class="input" id="province" name="province" type="text" maxlength="100"
                                   value="{{ old('province', $address?->province) }}" required
                                   @error('province') aria-invalid="true" aria-describedby="province-error" @enderror>
                            @error('province')<p class="alert-error field-error" id="province-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="admin-field">
                            <label for="city" class="admin-label">Kota / Kabupaten</label>
                            <input class="input" id="city" name="city" type="text" maxlength="100"
                                   value="{{ old('city', $address?->city) }}" required
                                   @error('city') aria-invalid="true" aria-describedby="city-error" @enderror>
                            @error('city')<p class="alert-error field-error" id="city-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="admin-field-grid">
                        <div class="admin-field">
                            <label for="district" class="admin-label">Kecamatan</label>
                            <input class="input" id="district" name="district" type="text" maxlength="100"
                                   value="{{ old('district', $address?->district) }}" required
                                   @error('district') aria-invalid="true" aria-describedby="district-error" @enderror>
                            @error('district')<p class="alert-error field-error" id="district-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="admin-field">
                            <label for="village" class="admin-label">Kelurahan / Desa</label>
                            <input class="input" id="village" name="village" type="text" maxlength="100"
                                   value="{{ old('village', $address?->village) }}" required
                                   @error('village') aria-invalid="true" aria-describedby="village-error" @enderror>
                            @error('village')<p class="alert-error field-error" id="village-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="admin-field">
                        <label for="street_address" class="admin-label">Alamat jalan lengkap (maks. 500 karakter)</label>
                        <textarea class="input admin-textarea" id="street_address" name="street_address" rows="3" maxlength="500" required
                                  @error('street_address') aria-invalid="true" aria-describedby="street_address-error" @enderror>{{ old('street_address', $address?->street_address) }}</textarea>
                        @error('street_address')<p class="alert-error field-error" id="street_address-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="admin-form-actions">
                        <button class="btn-primary" type="submit">Simpan Alamat</button>
                        <a class="btn-secondary" href="{{ route('profil.show') }}">Batal</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection
