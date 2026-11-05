@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Akun Pembeli</h1>
        <form method="GET" action="{{ route('admin.akun-pembeli.index') }}">
            <input class="input" name="cari" value="{{ request('cari') }}" placeholder="Cari nama/email">
            <select name="status">
                <option value="">Semua status</option>
                <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
            </select>
            <select name="asal">
                <option value="">Semua asal data</option>
                <option value="normal" @selected(request('asal') === 'normal')>Normal</option>
                <option value="developer_simulation" @selected(request('asal') === 'developer_simulation')>Simulasi</option>
            </select>
            <button class="btn-primary" type="submit">Cari</button>
        </form>
        @if ($accounts->isEmpty())
            <p>Belum ada akun pembeli.</p>
        @else
            <table>
                <thead><tr><th>Nama</th><th>Email</th><th>Status</th><th>Asal data</th><th>Tanggal daftar (WITA)</th></tr></thead>
                <tbody>
                @foreach ($accounts as $account)
                    <tr>
                        <td><a href="{{ route('admin.akun-pembeli.show', $account) }}">{{ $account->name }}</a></td>
                        <td>{{ $account->email }}</td>
                        <td>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td>
                            @if ($account->isSimulationAccount())
                                <span class="badge-simulasi">SIMULASI</span>
                            @else
                                Normal
                            @endif
                        </td>
                        <td>{{ $account->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $accounts->links() }}
        @endif
    </div>
@endsection
