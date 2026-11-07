@extends('layouts.app')

@section('content')
    <div class="developer-page">
        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Data</p>
                <h1 class="developer-title">Akun Simulasi</h1>
                <p class="developer-subtitle">Daftar read-only. Akun normal tidak tampil di sini. Kata sandi tidak pernah ditampilkan.</p>
            </div>
            <div class="developer-head-actions">
                <a class="btn-primary" href="{{ route('developer.akun.create') }}">Buat Akun Simulasi</a>
            </div>
        </header>

        <div class="card developer-panel">
            <form class="developer-filters" method="GET" action="{{ route('developer.akun-simulasi.index') }}">
                <div class="developer-filter-group developer-filter-grow">
                    <label class="developer-label" for="filter-run">Filter ID Run</label>
                    <input class="input" id="filter-run" name="run" value="{{ request('run') }}" placeholder="Filter run (simulation_run_id)">
                </div>
                <div class="developer-filter-actions">
                    <button class="btn-primary" type="submit">Filter</button>
                    @if (request()->filled('run'))
                        <a class="btn-secondary" href="{{ route('developer.akun-simulasi.index') }}">Reset filter</a>
                    @endif
                </div>
            </form>

            @if ($accounts->isEmpty())
                <div class="developer-empty">
                    <p class="developer-empty-title">Belum ada akun simulasi.</p>
                    <p class="developer-hint">Buat akun simulasi terlebih dahulu lewat menu Buat Akun.</p>
                </div>
            @else
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Run asal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($accounts as $account)
                            <tr>
                                <td class="developer-cell-primary">{{ $account->name }}</td>
                                <td><code class="developer-code">{{ $account->email }}</code></td>
                                <td>
                                    @if ($account->simulation_run_id)
                                        <a class="developer-cell-link" href="{{ route('developer.run.show', $account->simulation_run_id) }}">
                                            <code class="developer-code">{{ $account->simulation_run_id }}</code>
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <span class="status-chip {{ $account->is_active ? 'status-chip-success' : 'status-chip-muted' }}">
                                        <span>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="developer-pagination">
                    {{ $accounts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
