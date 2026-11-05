@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>{{ $category ? 'Ubah Kategori' : 'Tambah Kategori' }}</h1>
        <form method="POST" action="{{ $category ? route('admin.kategori.update', $category) : route('admin.kategori.store') }}">
            @csrf
            @if ($category)
                @method('PUT')
            @endif
            <div>
                <label for="name">Nama kategori (2–60 karakter)</label>
                <input class="input" id="name" name="name" value="{{ old('name', $category?->name) }}" required>
                @error('name')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <p>Slug dibuat otomatis dari nama dan dijamin unik.</p>
            <button class="btn-primary" type="submit">{{ $category ? 'Simpan perubahan' : 'Tambah kategori' }}</button>
            <a href="{{ route('admin.kategori.index') }}">Batal</a>
        </form>
    </div>
@endsection
