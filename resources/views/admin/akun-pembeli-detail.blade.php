@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Detail Akun Pembeli</h1>
        <p>Nama: {{ $account->name }}</p>
        <p>Email: {{ $account->email }}</p>
        <p>Status: {{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</p>
        <p>Asal data: {{ $account->origin }}
            @if ($account->isSimulationAccount())
                <span class="badge-simulasi">SIMULASI</span>
            @endif
        </p>
        <p>Jumlah pesanan: {{ $orderCount }} (pesanan dibangun di M3)</p>
        <form method="POST" action="{{ route('admin.akun-pembeli.status', $account) }}" onsubmit="return confirm('Ubah status akun ini?')">
            @csrf
            <div>
                <label for="is_active">Status baru</label>
                <select id="is_active" name="is_active">
                    <option value="1" @selected($account->is_active)>Aktif</option>
                    <option value="0" @selected(! $account->is_active)>Nonaktif</option>
                </select>
            </div>
            <div>
                <label for="reason">Alasan (opsional, maks 255 karakter)</label>
                <input class="input" id="reason" name="reason" value="{{ old('reason', $account->deactivation_reason) }}">
                @error('reason')<p class="alert-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary" type="submit">Simpan status</button>
        </form>
        <p><a href="{{ route('admin.akun-pembeli.index') }}">Kembali ke daftar</a></p>
    </div>
@endsection
