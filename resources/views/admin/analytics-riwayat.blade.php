{{-- M6 FR-05 ANL-52: riwayat event berpaginasi (20/baris, terbaru
dulu) — dibaca langsung dari tabel `events`, tanpa memanggil servis
Python. Hanya nama pembeli yang ditampilkan, tanpa email/kredensial
(keputusan M6 §3.4). Filter produk/jenis/periode — TANPA filter/kolom
asal data (keputusan M6 §3.7: nol label simulasi di UI admin). --}}
@extends('layouts.app')

@section('content')
<div class="admin-page">
    <header class="admin-head">
        <div>
            <p class="admin-eyebrow">Analitik &bull; Aktivitas &amp; Minat</p>
            <h1 class="admin-title">Riwayat Aktivitas</h1>
            <p class="admin-subtitle">Jejak event dilihat, keranjang, booking, pembelian, dan pembatalan — terbaru dulu, waktu WITA.</p>
        </div>
    </header>

    @php
        $typeLabels = [
            'view' => 'Dilihat',
            'add_to_cart' => 'Keranjang',
            'booking' => 'Booking',
            'purchase' => 'Pembelian',
            'cancel' => 'Pembatalan',
        ];
        $tz = config('ira.reporting_timezone', 'Asia/Makassar');
        $hasFilter = request()->filled('produk') || request()->filled('jenis') || request()->filled('dari') || request()->filled('sampai');
    @endphp

    <div class="card admin-panel">
        <form method="GET" action="{{ route('admin.aktivitas.riwayat') }}" class="admin-filters">
            <div class="admin-filter-group admin-filter-grow">
                <label for="filter-produk" class="admin-label">Produk</label>
                <select class="input" id="filter-produk" name="produk">
                    <option value="">Semua produk</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('produk', '') === (string) $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-filter-group">
                <label for="filter-jenis" class="admin-label">Jenis event</label>
                <select class="input" id="filter-jenis" name="jenis">
                    <option value="">Semua jenis</option>
                    @foreach ($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('jenis') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-filter-group">
                <label for="dari" class="admin-label">Dari tanggal</label>
                <input class="input" type="date" id="dari" name="dari" value="{{ request('dari') }}">
            </div>
            <div class="admin-filter-group">
                <label for="sampai" class="admin-label">Sampai tanggal</label>
                <input class="input" type="date" id="sampai" name="sampai" value="{{ request('sampai') }}">
            </div>
            <div class="admin-filter-actions">
                <button class="btn-primary" type="submit">Cari</button>
                @if ($hasFilter)
                    <a class="btn-secondary" href="{{ route('admin.aktivitas.riwayat') }}">Reset</a>
                @endif
            </div>
        </form>

        @if ($events->isEmpty())
            <div class="admin-empty">
                <p class="admin-empty-title">Belum ada aktivitas.</p>
                <p class="admin-empty-text">Event yang cocok dengan filter ini akan tampil di sini.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>Waktu (WITA)</th><th>Pembeli</th><th>Jenis event</th><th>Produk</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr>
                                <td class="admin-cell-nowrap">{{ $event->occurred_at ? $event->occurred_at->copy()->setTimezone($tz)->format('d M Y H:i') : '—' }}</td>
                                <td>{{ $event->user->name ?? '—' }}</td>
                                <td>{{ $typeLabels[$event->event_type] ?? $event->event_type }}</td>
                                <td class="admin-cell-primary">{{ $event->product->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $events->links() }}
        @endif
    </div>
</div>
@endsection
