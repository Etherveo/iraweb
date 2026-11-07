<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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
                'revenue_idr' => ['value' => null, 'text' => 'Belum dapat dihitung'],
                'transactions' => ['value' => null, 'text' => 'Belum dapat dihitung'],
                'units' => ['value' => null, 'text' => 'Belum dapat dihitung'],
                'purchases' => ['value' => null, 'text' => 'Belum dapat dihitung'],
            ],
            'vs_previous' => [],
            'buckets' => [],
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
            'revenue_idr' => ['value' => 150000, 'text' => null],
            'transactions' => ['value' => 3, 'text' => null],
            'units' => ['value' => 5, 'text' => null],
            'purchases' => ['value' => 5, 'text' => null],
        ],
        'vs_previous' => [
            'revenue_idr' => ['available' => true, 'change_ratio' => 0.5, 'descriptive_only' => true, 'significant' => null],
            'transactions' => ['available' => true, 'change_ratio' => 0.5, 'descriptive_only' => false, 'significant' => false],
            'units' => ['available' => false, 'text' => 'Belum dapat dihitung', 'reason' => 'tidak ada data pembanding'],
            'purchases' => ['available' => true, 'change_ratio' => 0.25, 'descriptive_only' => false, 'significant' => true],
        ],
        'buckets' => [
            ['bucket_start' => '2026-10-01', 'bucket_end' => '2026-10-01', 'revenue_idr' => 150000, 'units' => 5, 'transactions' => 3, 'purchases' => 5],
        ],
        'top_selling' => [
            ['key' => 'p1', 'name' => 'Pensil 2B', 'units' => 5, 'revenue_idr' => 150000],
        ],
        'least_selling' => [
            ['key' => 'p1', 'name' => 'Pensil 2B', 'units' => 5, 'revenue_idr' => 150000],
        ],
        'peak_day' => '2026-10-01',
        'empty' => false,
        'transactions_differ_from_purchases' => false,
        'warnings' => [],
    ];
}

/** Respons /v1/trend palsu (1 naik, 1 tanpa tren jelas). */
function anpFakeTrend(int $weeksFull = 9): array
{
    $naik = [
        'key' => 'p1', 'name' => 'Pensil 2B', 'label_code' => 'naik', 'label' => 'Naik',
        'strength' => 'kuat', 'n' => 9, 'beta' => 1.0, 'r' => 0.9, 'p' => 0.001,
        'q' => 0.01, 's' => 53, 'z' => 3.5, 'var' => 211.67, 'mean' => 12.0, 'change_abs' => 8.0,
    ];
    $datar = [
        'key' => 'p2', 'name' => 'Penghapus', 'label_code' => 'tidak_ada_tren_jelas',
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
        'ranking_rising' => $weeksFull < 8 ? [] : [$naik],
        'ranking_falling' => [],
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
            ['key' => 'p1', 'name' => 'Pensil 2B', 'views' => 100, 'add_to_carts' => 40, 'bookings' => 24, 'purchases' => 20, 'conversion_to_view' => 0.2],
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
        ->assertSee(route('admin.funnel'), false);
});
