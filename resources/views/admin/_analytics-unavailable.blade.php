{{-- M5 ANL-01: servis perhitungan tidak tersedia — halaman tetap 200,
tanpa angka nol yang menyesatkan. Bila kunci internal kosong, yang
tampil pesan konfigurasi (tanpa tombol coba lagi — mengulang tidak akan
membantu sebelum kunci diisi). --}}
<div class="card admin-panel analytics-unavailable" role="alert">
    <div class="analytics-state-icon" aria-hidden="true">
        @if ($config_error ?? false)
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 15V17M12 7V13M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        @else
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18A2 2 0 0 0 3.54 21H20.46A2 2 0 0 0 22.18 18L13.71 3.86A2 2 0 0 0 10.29 3.86Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endif
    </div>
    <div class="analytics-state-body">
        @if ($config_error ?? false)
            <h2 class="admin-section-title">Analitik belum dikonfigurasi</h2>
            <p class="analytics-note">Kunci internal analitik (<code class="admin-code">ANALYTICS_INTERNAL_KEY</code>) belum diisi di file <code class="admin-code">.env</code> server. Isi kuncinya, lalu muat ulang halaman ini.</p>
            <div class="analytics-state-actions">
                <a class="btn-primary" href="{{ request()->fullUrl() }}">Muat ulang halaman</a>
            </div>
        @else
            <h2 class="admin-section-title">Perhitungan sedang tidak tersedia</h2>
            <p class="analytics-note">Layanan perhitungan analitik tidak dapat dihubungi saat ini. Data toko tidak berubah; silakan coba lagi beberapa saat.</p>
            <div class="analytics-state-actions">
                <a class="btn-primary" href="{{ request()->fullUrl() }}">Coba lagi</a>
            </div>
        @endif
    </div>
</div>
