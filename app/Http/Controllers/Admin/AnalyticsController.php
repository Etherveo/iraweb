<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsCompareRequest;
use App\Http\Requests\AnalyticsPeriodRequest;
use App\Models\Event;
use App\Models\Product;
use App\Services\AnalyticsClient;
use App\Services\AnalyticsFactsBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

// M5 FR-01..03 (ANL-01) + M6 FR-04/FR-05 (ANL-41..56): halaman analitik
// admin — Dashboard, Tren, Funnel, Perbandingan, Aktivitas & Minat,
// Riwayat Aktivitas.
// Data dihitung oleh servis Python (AnalyticsClient); Laravel hanya
// menyusun facts (AnalyticsFactsBuilder) dan menampilkan hasilnya.
// Servis mati (client mengembalikan null) → halaman tetap 200 dengan
// pesan "Perhitungan sedang tidak tersedia" + tombol coba lagi
// (ANL-01: tidak crash, tidak menampilkan nol). Periode yang tidak valid
// ditolak oleh AnalyticsPeriodRequest / AnalyticsCompareRequest (maks
// 366 hari, ANL-01). Riwayat (ANL-52) TIDAK memanggil servis — baca
// langsung tabel `events`, jadi tidak ada cek kunci di sana.
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsClient $client,
        private readonly AnalyticsFactsBuilder $facts,
    ) {}

    // FR-01 Dashboard (ANL-01..12, K7/K8/K9): KPI + grafik penjualan +
    // Terlaris top 5 (ANL-12, ambang default 1 unit dibayar).
    public function dashboard(AnalyticsPeriodRequest $request): View
    {
        [$dari, $sampai] = $request->period();

        $payload = $this->facts->build($dari, $sampai);
        [$prevDari, $prevSampai] = $this->previousPeriod($dari, $sampai);
        $prev = $this->facts->build($prevDari, $prevSampai);
        $payload['previous_facts'] = $prev['facts'];
        $payload['previous_order_totals'] = $prev['order_totals'];
        $payload['top_n'] = 5;

        $configError = $this->keyMissing();
        $summary = $configError ? null : $this->client->summary($payload);

        return view('admin.analytics-dashboard', [
            'dari' => $dari,
            'sampai' => $sampai,
            'summary' => $summary,
            'unavailable' => $summary === null,
            'config_error' => $configError,
            'chart' => $summary === null || ($summary['empty'] ?? false)
                ? null
                : $this->salesChart($summary),
        ]);
    }

    // FR-02 Tren (K1/K2/K3/K13): tren per produk atas metrik units.
    // N_MIN 8 minggu penuh — periode yang lebih pendek tetap menampilkan
    // tabel, tetapi modul mengembalikan label "Data belum cukup".
    public function trend(AnalyticsPeriodRequest $request): View
    {
        [$dari, $sampai] = $request->period();

        $payload = $this->facts->build($dari, $sampai);
        $payload['metric'] = 'units';
        $payload['n_min_weeks'] = 8;

        $configError = $this->keyMissing();
        $trend = $configError ? null : $this->client->trend($payload);

        return view('admin.analytics-tren', [
            'dari' => $dari,
            'sampai' => $sampai,
            'trend' => $trend,
            'unavailable' => $trend === null,
            'config_error' => $configError,
        ]);
    }

    // FR-03 Funnel (I3): viewed → cart → booking → purchase (purchase =
    // dibayar, keputusan 2026-11-08). Angka memakai facts yang sama
    // dengan FR-01/FR-02, jadi konsisten dengan dashboard dan tren.
    public function funnel(AnalyticsPeriodRequest $request): View
    {
        [$dari, $sampai] = $request->period();

        $payload = $this->facts->build($dari, $sampai);

        $configError = $this->keyMissing();
        $funnel = $configError ? null : $this->client->funnel($payload);

        return view('admin.analytics-funnel', [
            'dari' => $dari,
            'sampai' => $sampai,
            'funnel' => $funnel,
            'unavailable' => $funnel === null,
            'config_error' => $configError,
            'has_events' => $funnel !== null
                && array_sum(array_column($funnel['stages'] ?? [], 'count')) > 0,
            'chart' => $funnel === null ? null : $this->funnelChart($funnel),
        ]);
    }

    // FR-04 Perbandingan (ANL-41..46, keputusan M6 §3.1/3.2/3.5): dua
    // periode A dan B; B default = periode berpanjang sama tepat sebelum
    // A (ANL-46, dihitung AnalyticsCompareRequest). Payload compare
    // dirakit dari dua kali build() — JANGAN kirim `categories`
    // (server menolaknya untuk /v1/compare, pelajaran 422 di M5).
    // Grafik garis harian A vs B diagregasi di Laravel dari facts
    // (modul tidak membawa deret harian).
    public function compare(AnalyticsCompareRequest $request): View
    {
        [[$dari, $sampai], [$pembandingDari, $pembandingSampai]] = $request->periods();

        $a = $this->facts->build($dari, $sampai);
        $b = $this->facts->build($pembandingDari, $pembandingSampai);
        $payload = [
            'facts_a' => $a['facts'],
            'facts_b' => $b['facts'],
            'products' => $a['products'],
            'period_a' => $a['period'],
            'period_b' => $b['period'],
            'order_totals_a' => $a['order_totals'],
            'order_totals_b' => $b['order_totals'],
            'top_n' => 5,
        ];

        $configError = $this->keyMissing();
        $compare = $configError ? null : $this->client->compare($payload);

        return view('admin.analytics-perbandingan', [
            'dari' => $dari,
            'sampai' => $sampai,
            'pembanding_dari' => $pembandingDari,
            'pembanding_sampai' => $pembandingSampai,
            'compare' => $compare,
            'unavailable' => $compare === null,
            'config_error' => $configError,
            'chart' => $compare === null || ($compare['empty'] ?? false)
                ? null
                : $this->compareChart($a['facts'], $b['facts'], $dari, $sampai, $pembandingDari, $pembandingSampai),
        ]);
    }

    // FR-05 Aktivitas & Minat (ANL-51..56): ringkasan minat per produk,
    // sorotan "Perlu ditinjau", pembanding. Filter kategori opsional
    // (ANL-54) diteruskan ke modul sebagai category_id; pengurutan tabel
    // (`urut`) dilakukan di sini atas hasil modul (ANL-54).
    public function interest(AnalyticsPeriodRequest $request): View
    {
        [$dari, $sampai] = $request->period();
        $categoryId = $request->categoryId();

        $payload = $this->facts->build($dari, $sampai);

        if ($categoryId !== null) {
            $payload['category_id'] = $categoryId;
        }

        $configError = $this->keyMissing();
        $interest = $configError ? null : $this->client->interest($payload);

        return view('admin.analytics-aktivitas', [
            'dari' => $dari,
            'sampai' => $sampai,
            'kategori' => $categoryId,
            'categories' => $payload['categories'],
            'interest' => $interest,
            'unavailable' => $interest === null,
            'config_error' => $configError,
            'products' => $interest === null
                ? []
                : $this->sortedInterestProducts($interest['products'] ?? [], $request->query('urut')),
            'sort' => (string) $request->query('urut', 'dilihat'),
        ]);
    }

    // FR-05 ANL-52: riwayat event berpaginasi (20/baris, terbaru dulu).
    // Baca langsung tabel `events` — TIDAK memanggil servis Python, jadi
    // tidak ada cek kunci dan tidak ada keadaan "tidak tersedia".
    // Hanya nama pembeli yang ditampilkan, tanpa email/kredensial
    // (keputusan M6 §3.4). Filter mengikuti pola daftar admin (M3a):
    // nilai di luar daftar yang dikenal diabaikan, bukan ditolak.
    // TIDAK ada filter/kolom asal data (keputusan M6 §3.7): keputusan
    // 2026-11-08 — nol label simulasi di UI admin; pembedaan asal
    // hanya ada di metadata + panel Developer.
    public function history(Request $request): View
    {
        $produk = $request->input('produk');
        $produkId = is_string($produk) && ctype_digit($produk) ? (int) $produk : null;

        // Batas tanggal WITA → UTC: `occurred_at` disimpan UTC, tapi
        // halaman menampilkan WITA — filter harus memakai hari WITA,
        // bukan tanggal UTC (whereDate pada UTC menggeser batas hari
        // sampai 8 jam). Keputusan M6 §3.7.
        $tz = (string) config('ira.reporting_timezone', 'Asia/Makassar');
        $from = $this->isDate($request->input('dari'))
            ? Carbon::parse($request->input('dari'), $tz)->startOfDay()->utc()
            : null;
        $to = $this->isDate($request->input('sampai'))
            ? Carbon::parse($request->input('sampai'), $tz)->endOfDay()->utc()
            : null;

        $events = Event::query()
            ->with(['user', 'product'])
            ->when($produkId !== null, fn ($q) => $q->where('product_id', $produkId))
            ->when(in_array($request->input('jenis'), Event::TYPES, true),
                fn ($q) => $q->where('event_type', $request->input('jenis')))
            ->when($from !== null, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.analytics-riwayat', [
            'events' => $events,
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // Kunci internal kosong → halaman menampilkan pesan konfigurasi
    // (200), bukan 500. Sengaja dicek DI SINI, bukan dengan menangkap
    // InvalidArgumentException dari client: client juga melempar jenis
    // yang sama untuk respons 422, dan keduanya tidak boleh tertukar.
    // Fail-fast client tetap dipertahankan sebagai pertahanan kedua.
    private function keyMissing(): bool
    {
        $key = config('analytics.key');

        return ! is_string($key) || $key === '';
    }

    // Periode sebelumnya dengan panjang yang sama, tepat sebelum periode
    // ini (K8: perbandingan vs periode sebelumnya). Semua tanggal di masa
    // lampau, jadi selalu lolos validasi builder.
    /** @return array{0: string, 1: string} */
    private function previousPeriod(string $dari, string $sampai): array
    {
        $start = Carbon::parse($dari);
        $end = Carbon::parse($sampai);
        // Carbon 3 diffInDays() mengembalikan float (mis. 29.9999999
        // untuk rentang 30 hari) — floor seperti PeriodResolver, supaya
        // subDays() mendapat bilangan bulat dan tanggalnya tepat.
        $days = (int) floor($start->diffInDays($end)) + 1;

        return [
            $start->copy()->subDays($days)->toDateString(),
            $end->copy()->subDays($days)->toDateString(),
        ];
    }

    // Konfigurasi grafik garis penjualan per bucket (Chart.js, dibundel
    // via Vite — tanpa CDN). Hanya data + opsi aman-JSON; pemformatan
    // sumbu Rp ditangani app.js (data-chart-rupiah).
    /** @return array<string, mixed> */
    private function salesChart(array $summary): array
    {
        // Bucket ada di sales_series.buckets (bukan kunci 'buckets' di
        // tingkat atas) — modul mengembalikan deret di sales_series.
        $buckets = $summary['sales_series']['buckets'] ?? [];

        return [
            'type' => 'line',
            'data' => [
                'labels' => array_map(
                    static fn (array $b): string => Carbon::parse($b['bucket_start'])->format('d M'),
                    $buckets,
                ),
                'datasets' => [[
                    'label' => 'Penjualan (Rp)',
                    'data' => array_column($buckets, 'revenue_idr'),
                    'borderColor' => '#6655F5',
                    'backgroundColor' => 'rgba(102, 85, 245, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 2,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['y' => ['beginAtZero' => true]],
            ],
        ];
    }

    // Konfigurasi grafik batang horizontal funnel (Chart.js via Vite).
    /** @return array<string, mixed> */
    private function funnelChart(array $funnel): array
    {
        $stages = $funnel['stages'] ?? [];

        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_column($stages, 'label'),
                'datasets' => [[
                    'label' => 'Jumlah event',
                    'data' => array_column($stages, 'count'),
                    'backgroundColor' => [
                        'rgba(102, 85, 245, 0.9)',
                        'rgba(102, 85, 245, 0.65)',
                        'rgba(102, 85, 245, 0.45)',
                        'rgba(102, 85, 245, 0.25)',
                    ],
                    'borderRadius' => 8,
                ]],
            ],
            'options' => [
                'indexAxis' => 'y',
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['x' => ['beginAtZero' => true]],
            ],
        ];
    }

    // Grafik garis pendapatan harian A vs B (ANL-44, keputusan M6 §3.2):
    // diagregasi di Laravel dari facts per (hari, produk) — modul tidak
    // membawa deret harian. Sumbu-x = hari ke-n dalam periode (periode
    // boleh berbeda panjang); hari tanpa penjualan di dalam periode
    // bernilai 0, sedangkan deret yang lebih pendek diisi null agar
    // garisnya berhenti, bukan nol yang menyesatkan.
    /** @return array<string, mixed> */
    private function compareChart(array $factsA, array $factsB, string $dariA, string $sampaiA, string $dariB, string $sampaiB): array
    {
        $daysA = $this->periodDays($dariA, $sampaiA);
        $daysB = $this->periodDays($dariB, $sampaiB);
        $maxDays = max($daysA, $daysB);

        $seriesA = $this->dailyRevenue($factsA, $dariA, $sampaiA);
        $seriesB = $this->dailyRevenue($factsB, $dariB, $sampaiB);

        $labels = [];
        $dataA = [];
        $dataB = [];

        for ($i = 0; $i < $maxDays; $i++) {
            $labels[] = 'Hari '.($i + 1);
            $dataA[] = $seriesA[$i] ?? null;
            $dataB[] = $seriesB[$i] ?? null;
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Periode A (Rp)',
                        'data' => $dataA,
                        'borderColor' => '#6655F5',
                        'backgroundColor' => 'rgba(102, 85, 245, 0.15)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointRadius' => 2,
                    ],
                    [
                        'label' => 'Periode B (Rp)',
                        'data' => $dataB,
                        'borderColor' => 'rgba(102, 85, 245, 0.45)',
                        'fill' => false,
                        'tension' => 0.3,
                        'pointRadius' => 2,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => true]],
                'scales' => ['y' => ['beginAtZero' => true]],
            ],
        ];
    }

    /** Pendapatan per hari dalam periode, sebagai daftar [0..n) — hari tanpa penjualan = 0. */
    /** @return list<int> */
    private function dailyRevenue(array $facts, string $dari, string $sampai): array
    {
        $byDate = [];

        foreach ($facts as $row) {
            $date = (string) ($row['date'] ?? '');

            if ($date === '') {
                continue;
            }

            $byDate[$date] = ($byDate[$date] ?? 0) + (int) ($row['revenue_idr'] ?? 0);
        }

        $days = $this->periodDays($dari, $sampai);
        $start = Carbon::parse($dari);
        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $series[] = $byDate[$start->copy()->addDays($i)->toDateString()] ?? 0;
        }

        return $series;
    }

    /** Jumlah hari inklusif antara dua tanggal Y-m-d (pola PeriodResolver). */
    private function periodDays(string $dari, string $sampai): int
    {
        return (int) floor(Carbon::parse($dari)->diffInDays(Carbon::parse($sampai))) + 1;
    }

    // Pengurutan tabel minat (ANL-54). Nilai di luar daftar yang dikenal
    // jatuh ke 'dilihat'. Seri → nama menaik, lalu id.
    /** @return list<array<string, mixed>> */
    private function sortedInterestProducts(array $products, mixed $sort): array
    {
        $sort = is_string($sort) ? $sort : 'dilihat';

        usort($products, function (array $a, array $b) use ($sort): int {
            $nameCmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));

            $cmp = match ($sort) {
                'pembelian' => (int) ($b['purchases'] ?? 0) <=> (int) ($a['purchases'] ?? 0),
                'rasio' => $this->ratioCmp($a['ratio'] ?? null, $b['ratio'] ?? null),
                'nama' => $nameCmp,
                default => (int) ($b['views'] ?? 0) <=> (int) ($a['views'] ?? 0),
            };

            return $cmp !== 0 ? $cmp : ($nameCmp !== 0 ? $nameCmp : (int) ($a['product_id'] ?? 0) <=> (int) ($b['product_id'] ?? 0));
        });

        return $products;
    }

    /** Rasio menurun; null (tidak terhitung) selalu terakhir. */
    private function ratioCmp(mixed $a, mixed $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }

        if ($a === null) {
            return 1;
        }

        if ($b === null) {
            return -1;
        }

        return (float) $b <=> (float) $a;
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
