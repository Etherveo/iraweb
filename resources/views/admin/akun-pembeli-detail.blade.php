@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4b (UI.md §6.1, §6.3, §9.4). Semua informasi akun,
         form POST ubah status + alasan, konfirmasi, dan teks tetap sama. --}}
    <div class="admin-page admin-page-narrow">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('admin.akun-pembeli.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke daftar</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Akun &bull; Akun pembeli</p>
                <h1 class="admin-title">Detail Akun Pembeli</h1>
                <p class="admin-subtitle">Ringkasan identitas akun pembeli dan pengaturan status aktif.</p>
            </div>
        </header>

        <div class="card admin-panel">
            <div class="admin-kv-grid">
                <div class="admin-kv-item">
                    <span class="admin-kv-label">Nama</span>
                    <strong class="admin-kv-value">{{ $account->name }}</strong>
                </div>
                <div class="admin-kv-item">
                    <span class="admin-kv-label">Email</span>
                    <span class="admin-kv-value">{{ $account->email }}</span>
                </div>
                <div class="admin-kv-item">
                    <span class="admin-kv-label">Status</span>
                    <span class="admin-kv-value">
                        @if ($account->is_active)
                            <span class="status-chip status-chip-success">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                                <span>Aktif</span>
                            </span>
                        @else
                            <span class="status-chip status-chip-muted">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                                <span>Nonaktif</span>
                            </span>
                        @endif
                    </span>
                </div>
                <div class="admin-kv-item">
                    <span class="admin-kv-label">Jumlah pesanan</span>
                    <strong class="admin-kv-value">{{ $orderCount }}</strong>
                </div>
                @if ($account->deactivated_at)
                    <div class="admin-kv-item">
                        <span class="admin-kv-label">Dinonaktifkan pada (WITA)</span>
                        <span class="admin-kv-value">{{ $account->deactivated_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="card admin-panel">
            <h2 class="admin-section-title">Ubah status akun</h2>
            <p class="admin-hint">Akun yang dinonaktifkan tidak dapat masuk dan sesi aktifnya langsung berakhir pada request berikutnya.</p>
            <form method="POST" action="{{ route('admin.akun-pembeli.status', $account) }}" class="admin-form" onsubmit="return confirm('Ubah status akun ini?')">
                @csrf
                <div class="admin-field">
                    <label for="is_active" class="admin-label">Status baru</label>
                    <select class="input" id="is_active" name="is_active">
                        <option value="1" @selected($account->is_active)>Aktif</option>
                        <option value="0" @selected(! $account->is_active)>Nonaktif</option>
                    </select>
                </div>

                <div class="admin-field">
                    <label for="reason" class="admin-label">Alasan (opsional, maks 255 karakter)</label>
                    <input class="input" id="reason" name="reason" value="{{ old('reason', $account->deactivation_reason) }}" placeholder="Contoh: Permintaan penutupan akun"
                           @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>
                    @error('reason')<p class="alert-error field-error" id="reason-error">{{ $message }}</p>@enderror
                </div>

                <div class="admin-form-actions">
                    <button class="btn-primary" type="submit">Simpan status</button>
                    <a class="btn-secondary" href="{{ route('admin.akun-pembeli.index') }}">Kembali ke daftar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
