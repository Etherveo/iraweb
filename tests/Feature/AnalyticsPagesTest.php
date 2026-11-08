<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// M5 langkah 4–6: halaman analitik admin (FR-01 Dashboard, FR-02 Tren,
// FR-03 Funnel). Tidak ada tes di sini yang butuh servis Python berjalan
// (client memakai Http::fake; kunci palsu 'test-key' via config).
// Kunci diisi di beforeEach karena AnalyticsClient fail-fast bila
// ANALYTICS_INTERNAL_KEY kosong (keputusan langkah 3).

beforeEach(function () {
    config(['analytics.key' => 'test-key']);
});

function anpAdmin(): User
{
    return User::factory()->admin()->create();
}

function anpBuyer(): User
{
    return User::factory()->create();
}

/** Respons /v1/summary palsu (KPI + vs periode sebelumnya + Terlaris). */
function anpFakeSummary(bool $empty = false): array
{
    if ($empty) {
        return [
            'kpis' => [
                'revenue_idr' => ['value' => null, 'vs_previous' => ['available' => false, 'text' => 'Belum dapat dihitung']],
                'transactions' => ['value' => null, 'text' => 'Belum dapat dihitung', 'vs_previous' => ['available' => false, 'text' => 'Belum dapat dihitung']],
                'units_sold' => ['value' => null, 'vs_previous' => ['available' => false, 'text' => 'Belum dapat dihitung']],
                'purchases' => ['value' => null, 'vs_previous' => ['available' => false, 'text' => 'Belum dapat dihitung']],
            ],
            'sales_series' => ['granularity' => 'day', 'buckets' => []],
            'top_selling' => [],
            'least_selling' => [],
            'peak_day' => null,
            'empty' => true,
            'transactions_differ_from_purchases' => false,
            'warnings' => [],
        ];
    }

    return [
        'kpis' => [
            'revenue_idr' => [
                'value' => 150000,
                'vs_previous' => ['available' => true, 'change_ratio' => 0.5, 'descriptive_only' => true, 'significant' => null],
            ],
            'transactions' => [
                'value' => 3, 'text' => null,
                'vs_previous' => ['available' => true, 'change_ratio' => 0.5, 'descriptive_only' => false, 'significant' => false],
            ],
            'units_sold' => [
                'value' => 5,
                'vs_previous' => ['available' => false, 'text' => 'Belum dapat dihitung', 'reason' => 'tidak ada data pembanding'],
            ],
            'purchases' => [
                'value' => 5,
                'vs_previous' => ['available' => true, 'change_ratio' => 0.25, 'descriptive_only' => false, 'significant' => true],
            ],
        ],
        'sales_series' => [
            'granularity' => 'day',
            'buckets' => [
                ['bucket_start' => '2026-10-01', 'bucket_end' => '2026-10-01', 'partial' => false, 'revenue_idr' => 150000],
            ],
        ],
        'top_selling' => [
            ['product_id' => 1, 'name' => 'Pensil 2B', 'units_sold' => 5, 'revenue_idr' => 150000, 'units_text' => '5 terjual', 'rank' => 1],
        ],
        'least_selling' => [
            ['product_id' => 1, 'name' => 'Pensil 2B', 'units_sold' => 5, 'revenue_idr' => 150000, 'units_text' => '5 terjual', 'rank' => 1],
        ],
        'peak_day' => ['date' => '2026-10-01', 'revenue_idr' => 150000, 'n_days' => 30],
        'empty' => false,
        'transactions_differ_from_purchases' => false,
        'warnings' => [],
    ];
}

/** Respons /v1/trend palsu (1 naik, 1 tanpa tren jelas). */
function anpFakeTrend(int $weeksFull = 9): array
{
    $naik = [
        'product_id' => 1, 'name' => 'Pensil 2B', 'label_code' => 'naik', 'label' => 'Naik',
        'strength' => 'kuat', 'n' => 9, 'beta' => 1.0, 'r' => 0.9, 'p' => 0.001,
        'q' => 0.01, 's' => 53, 'z' => 3.5, 'var' => 211.67, 'mean' => 12.0, 'change_abs' => 8.0,
    ];
    $datar = [
        'product_id' => 2, 'name' => 'Penghapus', 'label_code' => 'tidak_ada_tren_jelas',
        'label' => 'Tidak ada tren jelas', 'strength' => null, 'n' => 9, 'beta' => 0.1,
        'r' => 0.05, 'p' => 0.9, 'q' => 0.95, 's' => 2, 'z' => 0.07, 'var' => 193.33,
        'mean' => 11.0, 'change_abs' => 0.0,
    ];

    if ($weeksFull < 8) {
        $naik['label_code'] = 'data_belum_cukup';
        $naik['label'] = 'Data belum cukup';
        $naik['beta'] = $naik['r'] = $naik['change_abs'] = null;
        $datar['label_code'] = 'data_belum_cukup';
        $datar['label'] = 'Data belum cukup';
        $datar['beta'] = $datar['r'] = $datar['change_abs'] = null;
    }

    return [
        'metric' => 'units',
        'weeks_full' => $weeksFull,
        'n_min_weeks' => 8,
        'products' => [$naik, $datar],
        'rankings' => [
            'naik' => $weeksFull < 8 ? [] : [$naik],
            'turun' => [],
        ],
        'warnings' => [],
    ];
}

/** Respons /v1/funnel palsu (100 → 40 → 24 → 20). */
function anpFakeFunnel(): array
{
    return [
        'stages' => [
            ['stage' => 'view', 'label' => 'Dilihat', 'count' => 100, 'ratio_prev' => null, 'ratio_prev_text' => null, 'ratio_view' => 1.0, 'ratio_view_text' => null, 'anomaly' => false],
            ['stage' => 'add_to_cart', 'label' => 'Keranjang', 'count' => 40, 'ratio_prev' => 0.4, 'ratio_prev_text' => null, 'ratio_view' => 0.4, 'ratio_view_text' => null, 'anomaly' => false],
            ['stage' => 'booking', 'label' => 'Booking', 'count' => 24, 'ratio_prev' => 0.6, 'ratio_prev_text' => null, 'ratio_view' => 0.24, 'ratio_view_text' => null, 'anomaly' => false],
            ['stage' => 'purchase', 'label' => 'Pembelian', 'count' => 20, 'ratio_prev' => 0.833, 'ratio_prev_text' => null, 'ratio_view' => 0.2, 'ratio_view_text' => null, 'anomaly' => false],
        ],
        'largest_drop' => ['from_stage' => 'view', 'to_stage' => 'add_to_cart', 'from_label' => 'Dilihat', 'to_label' => 'Keranjang', 'from_count' => 100, 'to_count' => 40, 'diff' => 60, 'ratio' => 0.6],
        'anomalies' => [],
        'per_product' => [
            [
                'product_id' => 1, 'name' => 'Pensil 2B', 'category_id' => null, 'is_active' => true,
                'counts' => ['views' => 100, 'add_to_carts' => 40, 'bookings' => 24, 'purchases' => 20],
            ],
        ],
        'bookings_not_purchased' => 4,
        'cancels' => 1,
        'warnings' => [],
    ];
}

function anpFakeDown(): void
{
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });
}

test('M5 FR-01 dashboard renders KPI, chart data, top selling for admin', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/summary' => Http::response(anpFakeSummary(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard Analitik')
        ->assertSee('Rp 150.000')
        ->assertSee('+50,0% vs periode sebelumnya')
        ->assertSee('tidak signifikan')
        ->assertSee('signifikan')
        ->assertSee('Belum dapat dihitung')
        ->assertSee('Terlaris')
        ->assertSee('Pensil 2B')
        ->assertSee('5 unit terjual')
        ->assertSee('data-chart')
        ->assertDontSee('Perhitungan sedang tidak tersedia');
});

test('M5 FR-01 dashboard shows an honest empty state when there is no data', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/summary' => Http::response(anpFakeSummary(true), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Belum ada data pada periode ini')
        ->assertDontSee('Rp 150.000');
});

test('M5 FR-01 dashboard shows unavailable instead of zeros when the service is down', function () {
    anpFakeDown();

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Perhitungan sedang tidak tersedia')
        ->assertSee('Coba lagi')
        ->assertDontSee('Rp 150.000');
});

test('M5 analytics pages show a configuration message instead of a 500 when the internal key is missing', function () {
    config(['analytics.key' => '']);

    // Client tidak boleh dipanggil sama sekali — bila terpanggil,
    // fail-fast-nya akan melempar dan halaman jadi 500.
    Http::fake(fn () => throw new RuntimeException('client must not be called'));

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Analitik belum dikonfigurasi')
        ->assertSee('ANALYTICS_INTERNAL_KEY')
        ->assertDontSee('Perhitungan sedang tidak tersedia');

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/tren')
        ->assertOk()
        ->assertSee('Analitik belum dikonfigurasi');

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/funnel')
        ->assertOk()
        ->assertSee('Analitik belum dikonfigurasi');

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/perbandingan')
        ->assertOk()
        ->assertSee('Analitik belum dikonfigurasi');

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas')
        ->assertOk()
        ->assertSee('Analitik belum dikonfigurasi');

    // Riwayat (ANL-52) tidak memanggil servis — tetap jalan walau kunci
    // kosong, tanpa pesan konfigurasi.
    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas/riwayat')
        ->assertOk()
        ->assertSee('Riwayat Aktivitas')
        ->assertDontSee('Analitik belum dikonfigurasi');
});

test('M5 FR-02 trend renders per-product labels and rankings for admin', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/trend' => Http::response(anpFakeTrend(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/tren')
        ->assertOk()
        ->assertSee('Tren Produk')
        ->assertSee('Naik')
        ->assertSee('Tidak ada tren jelas')
        ->assertSee('Pensil 2B')
        ->assertSee('Penghapus')
        ->assertDontSee('stabil');
});

test('M5 FR-02 trend shows "Data belum cukup" when fewer than 8 full weeks', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/trend' => Http::response(anpFakeTrend(3), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/tren?dari=2026-10-01&sampai=2026-10-21')
        ->assertOk()
        ->assertSee('Data belum cukup');
});

test('M5 FR-02 trend shows unavailable instead of labels when the service is down', function () {
    anpFakeDown();

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/tren')
        ->assertOk()
        ->assertSee('Perhitungan sedang tidak tersedia')
        ->assertSee('Coba lagi');
});

test('M5 FR-03 funnel renders stages, ratios and largest drop for admin', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/funnel' => Http::response(anpFakeFunnel(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/funnel')
        ->assertOk()
        ->assertSee('Funnel Pembelian')
        ->assertSee('Dilihat')
        ->assertSee('Keranjang')
        ->assertSee('Booking')
        ->assertSee('Pembelian')
        ->assertSee('40,0%')
        ->assertSee('Penurunan terbesar')
        ->assertSee('Pensil 2B')
        ->assertSee('data-chart')
        ->assertDontSee('Perhitungan sedang tidak tersedia');
});

test('M5 FR-03 funnel shows unavailable instead of zeros when the service is down', function () {
    anpFakeDown();

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/funnel')
        ->assertOk()
        ->assertSee('Perhitungan sedang tidak tersedia')
        ->assertSee('Coba lagi');
});

test('M5 analytics pages reject guests and buyers (dashboard)', function () {
    $this->get('/admin')->assertRedirect('/masuk?aktor=admin_toko');
    $this->actingAs(anpBuyer(), 'web')->get('/admin')->assertForbidden();
});

test('M5 analytics pages reject guests and buyers (trend and funnel)', function () {
    $this->get('/admin/tren')->assertRedirect('/masuk?aktor=admin_toko');
    $this->get('/admin/funnel')->assertRedirect('/masuk?aktor=admin_toko');
    $this->actingAs(anpBuyer(), 'web')->get('/admin/tren')->assertForbidden();
    $this->actingAs(anpBuyer(), 'web')->get('/admin/funnel')->assertForbidden();
});

test('M5 analytics rejects an invalid period (start after end, future end, too long, bad format)', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/summary' => Http::response(anpFakeSummary(), 200),
    ]);

    $admin = anpAdmin();

    $this->actingAs($admin, 'web')
        ->get('/admin?dari=2026-10-30&sampai=2026-10-01')
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHasErrors('dari');

    $this->actingAs($admin, 'web')
        ->get('/admin?dari=2026-11-01&sampai=2099-01-01')
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHasErrors('dari');

    $this->actingAs($admin, 'web')
        ->get('/admin?dari=2025-01-01&sampai=2026-11-08')
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHasErrors('dari');

    $this->actingAs($admin, 'web')
        ->get('/admin?dari=ngawur&sampai=2026-11-08')
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHasErrors('dari');
});

test('M5 analytics sidebar links dashboard, trend and funnel', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/summary' => Http::response(anpFakeSummary(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin')
        ->assertOk()
        ->assertSee(route('admin.tren'), false)
        ->assertSee(route('admin.funnel'), false)
        ->assertSee(route('admin.perbandingan'), false)
        ->assertSee(route('admin.aktivitas'), false);
});

/** Respons /v1/compare palsu — bentuk ASLI compare_periods (rencana M6 §2).
 *  Angka sengaja konsisten: total = jumlah per produk (unit 19/5,
 *  transaksi 10/4, pembelian 17/4, Rp 212.000/54.000). */
function anpFakeCompare(bool $empty = false): array
{
    $metric = function ($a, $b, $p, $significance, $significant, $descriptive = false) {
        $change = $a - $b;
        $ratio = $b != 0 ? $change / $b : null;
        $rateA = $a / 30;
        $rateB = $b / 30;
        $perDay = $rateA - $rateB;
        $perDayRatio = $rateB != 0 ? $perDay / $rateB : null;

        $m = [
            'a' => $a, 'b' => $b, 'days_a' => 30, 'days_b' => 30, 'baseline' => 'B',
            'change' => $change, 'change_ratio' => $ratio,
            'change_ratio_text' => $ratio === null ? 'Belum dapat dihitung' : null,
            'rate_a' => $rateA, 'rate_b' => $rateB,
            'per_day_change' => $perDay, 'per_day_change_ratio' => $perDayRatio,
            'per_day_change_ratio_text' => $perDayRatio === null ? 'Belum dapat dihitung' : null,
            'lengths_differ' => false, 'p' => $p, 'significance' => $significance,
            'significant' => $significant, 'available' => true,
        ];

        if ($descriptive) {
            $m['descriptive_only'] = true;
        }

        return $m;
    };

    $data = [
        'algorithm_version' => 'ira-trend-analytics-ref/0.1.0',
        'timezone' => 'Asia/Makassar',
        'origin_filter' => 'all',
        'baseline' => 'B',
        'period_a' => ['start' => '2026-10-10', 'end' => '2026-11-08', 'days' => 30],
        'period_b' => ['start' => '2026-09-10', 'end' => '2026-10-09', 'days' => 30],
        'empty' => false,
        'totals' => [
            'revenue_idr' => $metric(212000, 54000, null, null, null, true),
            'units_sold' => $metric(19, 5, 0.007, 'Berbeda dari variasi acak', true),
            'purchases' => $metric(17, 4, 0.007, 'Berbeda dari variasi acak', true),
            'transactions' => $metric(10, 4, 0.18, 'Belum dapat dibedakan dari variasi acak', false),
            // Catatan: transactions_differ_from_purchases ada DI DALAM
            // totals (bukan di tingkat atas) — pelajaran bentuk M5.
            'transactions_differ_from_purchases' => true,
        ],
        'by_product' => [
            [
                'product_id' => 1, 'name' => 'Pensil 2B', 'category_id' => null, 'is_active' => true,
                'units_sold' => $metric(16, 2, 0.002, 'Berbeda dari variasi acak', true),
                'transactions' => $metric(8, 2, 0.11, 'Belum dapat dibedakan dari variasi acak', false),
                'purchases' => $metric(14, 2, 0.004, 'Berbeda dari variasi acak', true),
                'revenue_idr' => $metric(200000, 40000, null, null, null, true),
            ],
            [
                'product_id' => 2, 'name' => 'Penghapus', 'category_id' => null, 'is_active' => true,
                'units_sold' => $metric(3, 1, 0.63, 'Belum dapat dibedakan dari variasi acak', false),
                'transactions' => $metric(2, 1, 1.0, 'Belum dapat dibedakan dari variasi acak', false),
                'purchases' => $metric(3, 1, 0.63, 'Belum dapat dibedakan dari variasi acak', false),
                'revenue_idr' => $metric(12000, 8000, null, null, null, true),
            ],
            [
                'product_id' => 3, 'name' => 'Buku Tulis', 'category_id' => null, 'is_active' => true,
                'units_sold' => $metric(0, 2, 0.5, 'Belum dapat dibedakan dari variasi acak', false),
                'transactions' => $metric(0, 1, 1.0, 'Belum dapat dibedakan dari variasi acak', false),
                'purchases' => $metric(0, 1, 1.0, 'Belum dapat dibedakan dari variasi acak', false),
                'revenue_idr' => $metric(0, 6000, null, null, null, true),
            ],
        ],
        'biggest_increase' => [
            ['product_id' => 1, 'name' => 'Pensil 2B', 'units_a' => 16, 'units_b' => 2, 'per_day_change' => 16 / 30 - 2 / 30, 'change' => 14, 'change_ratio' => 7.0, 'change_ratio_text' => null, 'significance' => 'Berbeda dari variasi acak'],
            ['product_id' => 2, 'name' => 'Penghapus', 'units_a' => 3, 'units_b' => 1, 'per_day_change' => 3 / 30 - 1 / 30, 'change' => 2, 'change_ratio' => 2.0, 'change_ratio_text' => null, 'significance' => 'Belum dapat dibedakan dari variasi acak'],
        ],
        'biggest_decrease' => [
            ['product_id' => 3, 'name' => 'Buku Tulis', 'units_a' => 0, 'units_b' => 2, 'per_day_change' => 0 - 2 / 30, 'change' => -2, 'change_ratio' => -1.0, 'change_ratio_text' => null, 'significance' => 'Belum dapat dibedakan dari variasi acak'],
        ],
        'caveats' => ['Kejadian diasumsikan Poisson dan saling bebas; pelanggan berulang membuat p-value cenderung optimistis.'],
        'warnings' => [],
    ];

    if ($empty) {
        $data['empty'] = true;
    }

    return $data;
}

/** Respons /v1/interest palsu — bentuk ASLI analyze_interest (rencana M6 §2). */
function anpFakeInterest(): array
{
    $pensil = [
        'product_id' => 1, 'name' => 'Pensil 2B', 'category_id' => null, 'is_active' => true,
        'views' => 100, 'add_to_carts' => 40, 'bookings' => 24, 'purchases' => 2,
        'ratio' => 0.02, 'ratio_text' => null,
        'interval' => ['low' => 0.005, 'high' => 0.07, 'level' => 0.95],
        'cart_minus_purchase' => 38, 'sufficient_data' => true,
        'status' => 'Perlu ditinjau', 'needs_review' => true, 'is_comparator' => false,
        'anomaly' => false,
    ];
    $penghapus = [
        'product_id' => 2, 'name' => 'Penghapus', 'category_id' => null, 'is_active' => true,
        'views' => 20, 'add_to_carts' => 10, 'bookings' => 8, 'purchases' => 5,
        'ratio' => 0.25, 'ratio_text' => null,
        'interval' => ['low' => 0.11, 'high' => 0.47, 'level' => 0.95],
        'cart_minus_purchase' => 5, 'sufficient_data' => true,
        'status' => 'Tidak disorot', 'needs_review' => false, 'is_comparator' => false,
        'anomaly' => false,
    ];
    $buku = [
        'product_id' => 3, 'name' => 'Buku Tulis', 'category_id' => null, 'is_active' => true,
        'views' => 50, 'add_to_carts' => 20, 'bookings' => 15, 'purchases' => 12,
        'ratio' => 0.24, 'ratio_text' => null,
        'interval' => ['low' => 0.14, 'high' => 0.38, 'level' => 0.95],
        'cart_minus_purchase' => 8, 'sufficient_data' => true,
        'status' => 'Tidak disorot', 'needs_review' => false, 'is_comparator' => true,
        'anomaly' => false,
    ];

    return [
        'algorithm_version' => 'ira-trend-analytics-ref/0.1.0',
        'timezone' => 'Asia/Makassar',
        'period' => ['start' => '2026-10-10', 'end' => '2026-11-08', 'days' => 30],
        'origin_filter' => 'all',
        'category_id' => null,
        'params' => ['min_views' => 20, 'max_ratio' => 0.05, 'max_upper' => 0.10, 'comparator_min_ratio' => 0.10, 'comparator_min_purchases' => 10, 'comparator_max' => 3, 'confidence' => 0.95],
        'products_viewed' => 3,
        'total_views' => 170,
        'products' => [$pensil, $penghapus, $buku],
        'highlights' => [$pensil],
        'comparators' => [$buku],
        'warnings' => [],
    ];
}

test('M6 FR-04 compare renders metric cards, chart, biggest movers and per-product table for admin', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/compare' => Http::response(anpFakeCompare(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/perbandingan?dari=2026-10-10&sampai=2026-11-08&pembanding_dari=2026-09-10&pembanding_sampai=2026-10-09')
        ->assertOk()
        ->assertSee('Perbandingan Periode')
        ->assertSee('Rp 212.000')
        ->assertSee('+280,0%')
        ->assertSee('Berbeda dari variasi acak')
        ->assertSee('Perubahan terbesar naik')
        ->assertSee('Perubahan terbesar turun')
        ->assertSee('Pensil 2B')
        ->assertSee('Buku Tulis')
        ->assertSee('data-chart')
        ->assertDontSee('Perhitungan sedang tidak tersedia');
});

test('M6 FR-04 compare shows an honest empty state when both periods have no data', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/compare' => Http::response(anpFakeCompare(true), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/perbandingan')
        ->assertOk()
        ->assertSee('Belum ada data pada kedua periode')
        ->assertDontSee('Rp 212.000');
});

test('M6 FR-04 compare shows unavailable instead of zeros when the service is down', function () {
    anpFakeDown();

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/perbandingan')
        ->assertOk()
        ->assertSee('Perhitungan sedang tidak tersedia')
        ->assertSee('Coba lagi');
});

test('M6 FR-04 compare fills period B with the previous period (ANL-46)', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/compare' => Http::response(anpFakeCompare(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/perbandingan?dari=2026-10-10&sampai=2026-11-08&pembanding=sebelumnya')
        ->assertOk()
        ->assertSee('2026-09-10', false)
        ->assertSee('2026-10-09', false);
});

test('M6 FR-04 compare rejects an invalid comparison period', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/compare' => Http::response(anpFakeCompare(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/perbandingan?dari=2026-10-10&sampai=2026-11-08&pembanding_dari=2026-10-09&pembanding_sampai=2026-09-10')
        ->assertRedirect(route('admin.perbandingan'))
        ->assertSessionHasErrors('pembanding_dari');
});

test('M6 FR-05 interest renders summary, highlights, comparators and the product table for admin', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/interest' => Http::response(anpFakeInterest(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas')
        ->assertOk()
        ->assertSee('Aktivitas & Minat')
        ->assertSee('Perlu ditinjau')
        ->assertSee('Pensil 2B')
        ->assertSee('Pembanding')
        ->assertSee('Buku Tulis')
        ->assertSee('Lihat riwayat')
        ->assertSee('/admin/aktivitas/riwayat?produk=1', false)
        ->assertDontSee('Perhitungan sedang tidak tersedia');
});

test('M6 FR-05 interest shows unavailable instead of zeros when the service is down', function () {
    anpFakeDown();

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas')
        ->assertOk()
        ->assertSee('Perhitungan sedang tidak tersedia')
        ->assertSee('Coba lagi');
});

test('M6 FR-05 interest forwards the category filter to the service (ANL-54)', function () {
    $category = Category::factory()->create();

    Http::fake([
        'http://127.0.0.1:8001/v1/interest' => Http::response(anpFakeInterest(), 200),
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas?kategori='.$category->id)
        ->assertOk()
        ->assertSee($category->name);

    Http::assertSent(function ($request) use ($category) {
        return str_ends_with($request->url(), '/v1/interest')
            && ($request->data()['category_id'] ?? null) === $category->id;
    });
});

test('M6 FR-05 interest sorts the product table (ANL-54)', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/interest' => Http::response(anpFakeInterest(), 200),
    ]);

    $admin = anpAdmin();

    // Default: urut dilihat menurun (Pensil 100 > Buku 50).
    $default = $this->actingAs($admin, 'web')->get('/admin/aktivitas')->assertOk();
    $table = substr($default->getContent(), (int) strpos($default->getContent(), 'Minat per produk'));
    $this->assertTrue(strpos($table, 'Pensil 2B') < strpos($table, 'Buku Tulis'));

    // urut=pembelian: Buku (12) di atas Pensil (2).
    $byPurchase = $this->actingAs($admin, 'web')->get('/admin/aktivitas?urut=pembelian')->assertOk();
    $table2 = substr($byPurchase->getContent(), (int) strpos($byPurchase->getContent(), 'Minat per produk'));
    $this->assertTrue(strpos($table2, 'Buku Tulis') < strpos($table2, 'Pensil 2B'));
});

test('M6 FR-05 history renders real events with buyer name only, without calling the service', function () {
    // Riwayat tidak boleh memanggil servis Python sama sekali.
    Http::fake(fn () => throw new RuntimeException('service must not be called'));

    $buyer = anpBuyer();
    $product = Product::factory()->create(['name' => 'Pensil 2B']);
    Event::factory()->create([
        'event_type' => 'purchase', 'user_id' => $buyer->id, 'product_id' => $product->id,
        'occurred_at' => now(), 'origin' => 'normal',
    ]);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas/riwayat')
        ->assertOk()
        ->assertSee('Riwayat Aktivitas')
        ->assertSee($buyer->name)
        ->assertSee('Pensil 2B')
        ->assertSee('Pembelian')
        ->assertDontSee($buyer->email)
        // Keputusan 2026-11-08: nol label simulasi di UI admin.
        ->assertDontSee('Simulasi');
});

test('M6 FR-05 history date filter follows WITA days, not UTC dates', function () {
    // occurred_at disimpan UTC, halaman menampilkan WITA: event pukul
    // 02:00 WITA (18:00 UTC hari sebelumnya) harus masuk filter hari
    // WITA-nya, dan event pukul 02:00 WITA keesokan harinya tidak.
    $buyer = anpBuyer();
    $product = Product::factory()->create(['name' => 'Pensil 2B']);
    Event::factory()->create([
        'event_type' => 'view', 'user_id' => $buyer->id, 'product_id' => $product->id,
        'occurred_at' => Carbon::parse('2026-11-07 18:00', 'UTC'), 'origin' => 'normal',
    ]);
    Event::factory()->create([
        'event_type' => 'view', 'user_id' => $buyer->id, 'product_id' => $product->id,
        'occurred_at' => Carbon::parse('2026-11-08 18:00', 'UTC'), 'origin' => 'normal',
    ]);

    $page = $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas/riwayat?dari=2026-11-08&sampai=2026-11-08')
        ->assertOk();
    // Satu baris cocok (header + 1) — event 02:00 WITA 8 Nov masuk,
    // event 02:00 WITA 9 Nov tidak.
    $this->assertSame(2, substr_count($page->getContent(), '<tr>'));
});

test('M6 FR-05 history filters by product and type', function () {
    $productA = Product::factory()->create(['name' => 'Pensil 2B']);
    $productB = Product::factory()->create(['name' => 'Penghapus']);
    $buyerA = anpBuyer();
    $buyerB = anpBuyer();
    Event::factory()->create(['event_type' => 'view', 'user_id' => $buyerA->id, 'product_id' => $productA->id, 'origin' => 'normal']);
    Event::factory()->create(['event_type' => 'purchase', 'user_id' => $buyerB->id, 'product_id' => $productB->id, 'origin' => 'developer_simulation', 'simulation_run_id' => (string) Str::ulid()]);

    // Nama pembeli dipakai sebagai penanda baris (nama produk juga ada
    // di dropdown filter, jadi tidak bisa dipakai assertDontSee).
    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas/riwayat?produk='.$productB->id)
        ->assertOk()
        ->assertSee($buyerB->name)
        ->assertDontSee($buyerA->name);

    $this->actingAs(anpAdmin(), 'web')
        ->get('/admin/aktivitas/riwayat?jenis=purchase')
        ->assertOk()
        ->assertSee($buyerB->name)
        ->assertDontSee($buyerA->name);
});

test('M6 FR-05 history paginates 20 events per page, newest first', function () {
    $buyer = anpBuyer();

    for ($i = 1; $i <= 21; $i++) {
        $product = Product::factory()->create(['name' => 'Produk Riwayat '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        Event::factory()->create([
            'event_type' => 'view', 'user_id' => $buyer->id, 'product_id' => $product->id,
            'occurred_at' => now()->subMinutes(21 - $i), 'origin' => 'normal',
        ]);
    }

    $page1 = $this->actingAs(anpAdmin(), 'web')->get('/admin/aktivitas/riwayat')->assertOk();
    $this->assertSame(21, substr_count($page1->getContent(), '<tr>')); // header + 20
    $this->assertStringContainsString('page=2', $page1->getContent());

    $page2 = $this->actingAs(anpAdmin(), 'web')->get('/admin/aktivitas/riwayat?page=2')->assertOk();
    $this->assertSame(2, substr_count($page2->getContent(), '<tr>')); // header + 1
    $page2->assertSee('Produk Riwayat 01');
});

test('M6 analytics pages reject guests and buyers (compare, interest, history)', function () {
    $this->get('/admin/perbandingan')->assertRedirect('/masuk?aktor=admin_toko');
    $this->get('/admin/aktivitas')->assertRedirect('/masuk?aktor=admin_toko');
    $this->get('/admin/aktivitas/riwayat')->assertRedirect('/masuk?aktor=admin_toko');

    $buyer = anpBuyer();
    $this->actingAs($buyer, 'web')->get('/admin/perbandingan')->assertForbidden();
    $this->actingAs($buyer, 'web')->get('/admin/aktivitas')->assertForbidden();
    $this->actingAs($buyer, 'web')->get('/admin/aktivitas/riwayat')->assertForbidden();
});
