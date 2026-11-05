@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Masuk</h1>
        <p>Satu formulir masuk untuk semua aktor. Pilih aktor di atas, lalu masuk dengan email dan kata sandi akun itu. Akun yang tidak sesuai aktor yang dipilih ditolak dengan pesan yang sama seperti kata sandi salah.</p>
        <nav aria-label="Pilih aktor">
            <a href="{{ route('masuk', ['aktor' => 'pembeli']) }}">Pembeli</a>
            |
            <a href="{{ route('masuk', ['aktor' => 'admin_toko']) }}">Admin Toko</a>
            |
            <a href="{{ route('masuk', ['aktor' => 'developer']) }}">Developer</a>
        </nav>
        <form method="POST" action="{{ route('masuk') }}">
            @csrf
            <fieldset>
                <legend>Pilih aktor</legend>
                <label>
                    <input type="radio" name="aktor" value="pembeli" {{ old('aktor', $aktor ?? 'pembeli') === 'pembeli' ? 'checked' : '' }} required>
                    Pembeli
                </label>
                <label>
                    <input type="radio" name="aktor" value="admin_toko" {{ old('aktor', $aktor ?? 'pembeli') === 'admin_toko' ? 'checked' : '' }}>
                    Admin Toko
                </label>
                <label>
                    <input type="radio" name="aktor" value="developer" {{ old('aktor', $aktor ?? 'pembeli') === 'developer' ? 'checked' : '' }}>
                    Developer
                </label>
                @error('aktor')<p class="alert-error">{{ $message }}</p>@enderror
            </fieldset>
            <div>
                <label for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password">Kata sandi</label>
                <input class="input" id="password" type="password" name="password" required>
                @error('password')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary" type="submit">Masuk</button>
        </form>
        <p>Belum punya akun pembeli? <a href="{{ route('daftar') }}">Daftar pembeli</a> — akun Admin Toko dan Developer hanya dibuat lewat provisioning, tidak bisa daftar sendiri.</p>
    </div>
@endsection
