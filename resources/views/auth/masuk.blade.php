@extends('layouts.app')

@section('content')
    @php($aktorTerpilih = old('aktor', $aktor ?? 'pembeli'))
    {{-- UI polish Tahap 1 (UI.md §9.1 + Figma "Masuk pembeli"). Perilaku
         sama seperti sebelumnya: satu formulir untuk semua aktor, field
         aktor/email/password, POST ke route('masuk'). Sakelar aktor adalah
         radio biasa yang ditata sebagai segmented control (tanpa JS);
         ?aktor=... tetap memilih aktor awal. --}}
    <div class="auth-page">
        <section class="auth-brand" aria-labelledby="auth-brand-title">
            <p class="auth-eyebrow">Alat tulis, banyak kemungkinan.</p>
            <h2 id="auth-brand-title" class="auth-brand-title">Mulai ide baru bersama Ira.</h2>
            <p class="auth-brand-text">Dari catatan kelas sampai rencana harian. Temukan perlengkapan yang pas untukmu.</p>
            <div class="auth-brand-art" aria-hidden="true">
                <div class="auth-brand-tile">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.4 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7.4"/><path d="M2 6h4"/><path d="M2 10h4"/><path d="M2 14h4"/><path d="M2 18h4"/><path d="M21.378 5.626a1 1 0 1 0-3.004-3.004l-5.01 5.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/></svg>
                </div>
            </div>
            <p class="auth-brand-foot">Ira Stationery &bull; Gorontalo</p>
        </section>

        <div class="auth-card">
            <p class="auth-eyebrow auth-eyebrow-primary">Masuk akun</p>
            <h1 class="auth-title">Selamat datang kembali</h1>
            <p class="auth-subtitle">Satu formulir masuk untuk semua aktor. Pilih aktor, lalu masuk dengan email dan kata sandi akun itu.</p>

            <form method="POST" action="{{ route('masuk') }}" class="auth-form">
                @csrf
                <fieldset class="auth-fieldset">
                    <legend class="auth-label">Pilih aktor</legend>
                    <div class="segmented" @error('aktor') aria-describedby="aktor-error" @enderror>
                        <label class="segmented-option">
                            <input type="radio" name="aktor" value="pembeli" {{ $aktorTerpilih === 'pembeli' ? 'checked' : '' }} required>
                            <span>Pembeli</span>
                        </label>
                        <label class="segmented-option">
                            <input type="radio" name="aktor" value="admin_toko" {{ $aktorTerpilih === 'admin_toko' ? 'checked' : '' }}>
                            <span>Admin Toko</span>
                        </label>
                        <label class="segmented-option">
                            <input type="radio" name="aktor" value="developer" {{ $aktorTerpilih === 'developer' ? 'checked' : '' }}>
                            <span>Developer</span>
                        </label>
                    </div>
                    @error('aktor')<p class="field-error" id="aktor-error">{{ $message }}</p>@enderror
                </fieldset>

                <div class="auth-field">
                    <label for="email" class="auth-label">Email</label>
                    <div class="input-icon">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    </div>
                    @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
                </div>

                <div class="auth-field">
                    <label for="password" class="auth-label">Kata sandi</label>
                    <div class="input-icon">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input class="input input-has-toggle" id="password" type="password" name="password" autocomplete="current-password" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        {{-- Eye toggle: tersembunyi tanpa JS (atribut hidden dilepas oleh app.js). --}}
                        <button type="button" class="input-toggle" data-password-toggle="password" aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi" hidden>
                            <svg data-icon-show aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-icon-hide aria-hidden="true" hidden xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </div>
                    @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
                </div>

                <button class="btn-primary btn-block" type="submit">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    <span>Masuk</span>
                </button>
            </form>

            <p class="auth-foot">Belum punya akun pembeli? <a href="{{ route('daftar') }}">Daftar pembeli</a></p>
            <p class="auth-note">Akun Admin Toko dan Developer hanya dibuat lewat provisioning, tidak bisa daftar sendiri.</p>
        </div>
    </div>
@endsection
