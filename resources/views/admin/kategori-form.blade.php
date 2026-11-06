@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4a (UI.md §6.3, §9.4 + Figma "Tambah/Ubah kategori").
         Field, method PUT/POST, dan label tetap sama. --}}
    <div class="admin-page admin-page-narrow">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('admin.kategori.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke kategori</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko &bull; Kategori produk</p>
                <h1 class="admin-title">{{ $category ? 'Ubah Kategori' : 'Tambah Kategori' }}</h1>
                <p class="admin-subtitle">Atur nama kategori untuk mengelompokkan produk di katalog.</p>
            </div>
        </header>

        <div class="card admin-panel">
            <form method="POST" action="{{ $category ? route('admin.kategori.update', $category) : route('admin.kategori.store') }}" class="admin-form">
                @csrf
                @if ($category)
                    @method('PUT')
                @endif

                <div class="admin-field">
                    <label for="name" class="admin-label">Nama kategori (2–60 karakter)</label>
                    <input class="input" id="name" name="name" value="{{ old('name', $category?->name) }}" required
                           @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    @error('name')<p class="alert-error field-error" id="name-error">{{ $message }}</p>@enderror
                    <p class="admin-hint">Slug dibuat otomatis dari nama dan dijamin unik.</p>
                </div>

                <div class="admin-form-actions">
                    <button class="btn-primary" type="submit">{{ $category ? 'Simpan perubahan' : 'Tambah kategori' }}</button>
                    <a class="btn-secondary" href="{{ route('admin.kategori.index') }}">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
