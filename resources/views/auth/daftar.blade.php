@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Daftar Pembeli</h1>
        <form method="POST" action="{{ route('daftar') }}">
            @csrf
            <div>
                <label for="name">Nama</label>
                <input class="input" id="name" name="name" value="{{ old('name') }}" required>
                @error('name')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password">Kata sandi (minimal 8 karakter)</label>
                <input class="input" id="password" type="password" name="password" required>
                @error('password')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation">Konfirmasi kata sandi</label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" required>
            </div>
            <button class="btn-primary" type="submit">Daftar</button>
        </form>
        <p>Sudah punya akun? <a href="{{ route('masuk') }}">Masuk pembeli</a></p>
    </div>
@endsection
