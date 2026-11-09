{{-- Instruksi pengambilan di toko (jadwal pickup, keputusan Ren
     2026-11-09). Hanya tampil bila pesanan punya jadwal (pickup_at
     terisi); pesanan lama dan pesanan simulasi tidak punya jadwal. --}}
@if ($order->pickup_at)
    <p class="admin-info-box">
        <strong>Pengambilan di toko.</strong>
        Ambil pesanan di toko Ira Stationery pada {{ $order->pickup_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y') }}, pukul {{ $order->pickup_at?->setTimezone(config('ira.reporting_timezone'))->format('H:i') }} WITA.
    </p>
@endif
