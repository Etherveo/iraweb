@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Masuk Developer</h1>
        <p>Panel Developer terpisah dari Admin Toko dan Pembeli. Akun web ditolak di form ini.</p>
        <form method="POST" action="{{ route('developer.masuk') }}">
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
            <button class="btn-primary" type="submit">Masuk sebagai Developer</button>
        </form>
    </div>
@endsection
