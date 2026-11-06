@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 3 (UI.md §9.1 + Figma "Daftar pembeli"). Perilaku
         sama seperti sebelumnya: hanya pembeli yang bisa daftar, field
         name/email/password/password_confirmation, POST ke route('daftar'). --}}
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
            <p class="auth-eyebrow auth-eyebrow-primary">Akun pembeli</p>
            <h1 class="auth-title">Buat akun pembeli</h1>
            <p class="auth-subtitle">Satu akun untuk belanja dan melihat riwayat pesananmu di Ira Stationery.</p>

            <form method="POST" action="{{ route('daftar') }}" class="auth-form">
                @csrf
                <div class="auth-field">
                    <label for="name" class="auth-label">Nama</label>
                    <div class="input-icon">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input class="input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required
                               @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    </div>
                    @error('name')<p class="field-error" id="name-error">{{ $message }}</p>@enderror
                </div>

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
                    <label for="password" class="auth-label">Kata sandi (minimal 8 karakter)</label>
                    <div class="input-icon">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input class="input input-has-toggle" id="password" type="password" name="password" autocomplete="new-password" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <button type="button" class="input-toggle" data-password-toggle="password" aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi" hidden>
                            <svg data-icon-show aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-icon-hide aria-hidden="true" hidden xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </div>
                    @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
                </div>

                <div class="auth-field">
                    <label for="password_confirmation" class="auth-label">Konfirmasi kata sandi</label>
                    <div class="input-icon">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input class="input input-has-toggle" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                        <button type="button" class="input-toggle" data-password-toggle="password_confirmation" aria-controls="password_confirmation" aria-pressed="false" aria-label="Tampilkan konfirmasi kata sandi" hidden>
                            <svg data-icon-show aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-icon-hide aria-hidden="true" hidden xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </div>
                </div>

                <button class="btn-primary btn-block" type="submit">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    <span>Daftar &amp; mulai belanja</span>
                </button>
            </form>

            <p class="auth-foot">Sudah punya akun? <a href="{{ route('masuk') }}">Masuk pembeli</a></p>
            <p class="auth-note">Hanya pembeli yang bisa daftar sendiri. Akun Admin Toko dan Developer dibuat lewat provisioning.</p>
        </div>
    </div>
@endsection
