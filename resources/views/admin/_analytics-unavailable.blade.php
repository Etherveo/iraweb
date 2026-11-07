{{-- M5 ANL-01: servis perhitungan tidak tersedia — halaman tetap 200,
tanpa angka nol yang menyesatkan. --}}
<div class="card analytics-unavailable" role="alert">
    <h2>Perhitungan sedang tidak tersedia</h2>
    <p>Layanan perhitungan analitik tidak dapat dihubungi saat ini. Data toko tidak berubah; silakan coba lagi beberapa saat.</p>
    <a class="btn-primary" href="{{ request()->fullUrl() }}">Coba lagi</a>
</div>
