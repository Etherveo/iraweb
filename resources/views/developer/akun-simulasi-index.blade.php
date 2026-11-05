@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Akun Simulasi</h1>
        <p>Daftar read-only. Akun normal tidak tampil di sini. Kata sandi tidak pernah ditampilkan.</p>
        <form method="GET" action="{{ route('developer.akun-simulasi.index') }}">
            <input class="input" name="run" value="{{ request('run') }}" placeholder="Filter run (simulation_run_id)">
            <button class="btn-primary" type="submit">Filter</button>
        </form>
        @if ($accounts->isEmpty())
            <p>Belum ada akun simulasi.</p>
        @else
            <table>
                <thead><tr><th>Nama</th><th>Email</th><th>Run asal</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($accounts as $account)
                    <tr>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->email }}</td>
                        <td>{{ $account->simulation_run_id ?? '-' }}</td>
                        <td>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $accounts->links() }}
        @endif
    </div>
@endsection
