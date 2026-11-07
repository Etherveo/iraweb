{{-- M5 ANL-01: servis perhitungan tidak tersedia — halaman tetap 200,
tanpa angka nol yang menyesatkan. Bila kunci internal kosong, yang
tampil pesan konfigurasi (tanpa tombol coba lagi — mengulang tidak akan
membantu sebelum kunci diisi). --}}
<div class="card analytics-unavailable" role="alert">
    @if ($config_error ?? false)
        <h2>Analitik belum dikonfigurasi</h2>
        <p>Kunci internal analitik (ANALYTICS_INTERNAL_KEY) belum diisi di file .env server. Isi kuncinya, lalu muat ulang halaman ini.</p>
        <a class="btn-primary" href="{{ request()->fullUrl() }}">Muat ulang halaman</a>
    @else
        <h2>Perhitungan sedang tidak tersedia</h2>
        <p>Layanan perhitungan analitik tidak dapat dihubungi saat ini. Data toko tidak berubah; silakan coba lagi beberapa saat.</p>
        <a class="btn-primary" href="{{ request()->fullUrl() }}">Coba lagi</a>
    @endif
</div>
