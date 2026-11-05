@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Masuk Admin Toko</h1>
        <p>Form terpisah. Hanya akun role Admin Toko yang diterima di sini.</p>
        <form method="POST" action="{{ route('admin.masuk') }}">
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
            <button class="btn-primary" type="submit">Masuk sebagai Admin Toko</button>
        </form>
    </div>
@endsection
