@extends('layouts.app')

@section('content')
    <div class="developer-page developer-page-narrow">
        <nav class="developer-back-nav" aria-label="Breadcrumb">
            <a class="developer-back-link" href="{{ route('developer.akun-simulasi.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Lihat daftar Akun Simulasi</span>
            </a>
        </nav>

        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Aksi 1</p>
                <h1 class="developer-title">Buat Akun Simulasi</h1>
                <p class="developer-subtitle">Satu aksi = satu run (SIM-02). Akun dibuat dengan role pembeli, origin <code>developer_simulation</code>, email <code>@sim.ira-stationery.test</code>, dan 1 alamat. Kata sandi bersama diambil dari <code>SIM_ACCOUNT_PASSWORD</code> dan tidak pernah ditampilkan (SIM-08).</p>
            </div>
        </header>

        @unless ($passwordConfigured)
            <p class="developer-info-box developer-info-box--warn"><strong>SIM_ACCOUNT_PASSWORD belum diisi di .env</strong> — pembuatan akun simulasi akan ditolak (SIM-08). Isi variabel itu di laptop sebelum membuat akun.</p>
        @endunless

        <p class="developer-info-box">Saat ini ada <strong>{{ $simAccountCount }}</strong> akun simulasi. Biodata dibuat oleh generator (Markov + KNN + Naive Bayes); mode deterministik dengan seed yang sama menghasilkan biodata yang sama (SIM-05).</p>

        <div class="card developer-panel">
            <form class="developer-form" method="POST" action="{{ route('developer.akun.store') }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                <div class="developer-field">
                    <label class="developer-label" for="count">Jumlah akun (maks {{ $maxAccounts }})</label>
                    <input class="input" type="number" id="count" name="count" value="{{ old('count', 10) }}" min="1" max="{{ $maxAccounts }}" step="1" required
                           @error('count') aria-invalid="true" aria-describedby="count-error" @enderror>
                    @error('count')
                        <p class="field-error" id="count-error">{{ $message }}</p>
                    @enderror
                </div>

                @include('developer.partials._seed-fields', ['defaultMode' => $defaultMode, 'defaultSeed' => $defaultSeed])

                <div class="developer-field">
                    <label class="developer-label" for="offset">Offset</label>
                    <input class="input" type="number" id="offset" name="offset" value="{{ old('offset', 0) }}" min="0" step="1"
                           @error('offset') aria-invalid="true" aria-describedby="offset-error" @enderror>
                    <p class="developer-hint">Offset menggeser irisan biodata dari aliran yang sama: seed + offset + jumlah yang sama selalu menghasilkan akun yang sama (SIM-05).</p>
                    @error('offset')
                        <p class="field-error" id="offset-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="developer-form-actions">
                    <button class="btn-primary" type="submit">Buat akun simulasi</button>
                    <a class="btn-secondary" href="{{ route('developer.dashboard') }}">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
