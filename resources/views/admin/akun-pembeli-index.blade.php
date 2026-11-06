@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 4b (UI.md §6.6, §9.4). Semua filter GET (cari,
         status, asal), lencana SIMULASI, pagination, dan teks asersi tetap sama. --}}
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Akun &bull; Akun pembeli</p>
                <h1 class="admin-title">Akun Pembeli</h1>
                <p class="admin-subtitle">Kelola status akun pembeli dan pantau asal data pendaftaran (normal atau simulasi).</p>
            </div>
        </header>

        <div class="card admin-panel">
            <form method="GET" action="{{ route('admin.akun-pembeli.index') }}" class="admin-filters">
                <div class="admin-filter-group admin-filter-grow">
                    <label for="filter-cari" class="admin-label">Cari pembeli</label>
                    <input class="input" id="filter-cari" name="cari" value="{{ request('cari') }}" placeholder="Cari nama/email">
                </div>
                <div class="admin-filter-group">
                    <label for="filter-status" class="admin-label">Status</label>
                    <select class="input" id="filter-status" name="status">
                        <option value="">Semua status</option>
                        <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>
                <div class="admin-filter-group">
                    <label for="filter-asal" class="admin-label">Asal data</label>
                    <select class="input" id="filter-asal" name="asal">
                        <option value="">Semua asal data</option>
                        <option value="normal" @selected(request('asal') === 'normal')>Normal</option>
                        <option value="developer_simulation" @selected(request('asal') === 'developer_simulation')>Simulasi</option>
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button class="btn-primary" type="submit">Cari</button>
                    @if (request()->filled('cari') || request()->filled('status') || request()->filled('asal'))
                        <a class="btn-secondary" href="{{ route('admin.akun-pembeli.index') }}">Reset</a>
                    @endif
                </div>
            </form>

            @if ($accounts->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada akun pembeli.</p>
                    <p class="admin-empty-text">Akun pembeli yang mendaftar atau dibuat lewat simulator akan tampil di sini.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Asal data</th>
                                <th>Tanggal daftar (WITA)</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($accounts as $account)
                            <tr>
                                <td data-label="Nama">
                                    <a class="admin-cell-link" href="{{ route('admin.akun-pembeli.show', $account) }}">{{ $account->name }}</a>
                                </td>
                                <td data-label="Email">{{ $account->email }}</td>
                                <td data-label="Status">
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
                                </td>
                                <td data-label="Asal data">
                                    @if ($account->isSimulationAccount())
                                        <span class="badge-simulasi">SIMULASI</span>
                                    @else
                                        <span>Normal</span>
                                    @endif
                                </td>
                                <td data-label="Tanggal daftar (WITA)">
                                    <span class="admin-cell-nowrap">{{ $account->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</span>
                                </td>
                                <td data-label="Aksi">
                                    <a class="admin-action-link" href="{{ route('admin.akun-pembeli.show', $account) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $accounts->links() }}</div>
            @endif
        </div>
    </div>
@endsection
