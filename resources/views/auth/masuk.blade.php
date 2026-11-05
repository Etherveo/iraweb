@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Masuk Pembeli</h1>
        <p>Pintu masuk khusus pembeli. Akun Admin Toko dan Developer ditolak di form ini.</p>
        <form method="POST" action="{{ route('masuk') }}">
            @csrf
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
            <button class="btn-primary" type="submit">Masuk sebagai pembeli</button>
        </form>
        <p>Belum punya akun? <a href="{{ route('daftar') }}">Daftar pembeli</a></p>
    </div>
@endsection
