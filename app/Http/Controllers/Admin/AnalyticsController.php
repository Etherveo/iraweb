<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsPeriodRequest;
use App\Services\AnalyticsClient;
use App\Services\AnalyticsFactsBuilder;
use Carbon\Carbon;
use Illuminate\View\View;

// M5 FR-01..03 (ANL-01): halaman analitik admin — Dashboard, Tren, Funnel.
// Data dihitung oleh servis Python (AnalyticsClient); Laravel hanya
// menyusun facts (AnalyticsFactsBuilder) dan menampilkan hasilnya.
// Servis mati (client mengembalikan null) → halaman tetap 200 dengan
// pesan "Perhitungan sedang tidak tersedia" + tombol coba lagi
// (ANL-01: tidak crash, tidak menampilkan nol). Periode yang tidak valid
// ditolak oleh AnalyticsPeriodRequest (maks 366 hari, ANL-01).
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

        $summary = $this->client->summary($payload);

        return view('admin.analytics-dashboard', [
            'dari' => $dari,
            'sampai' => $sampai,
            'summary' => $summary,
            'unavailable' => $summary === null,
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

        $trend = $this->client->trend($payload);

        return view('admin.analytics-tren', [
            'dari' => $dari,
            'sampai' => $sampai,
            'trend' => $trend,
            'unavailable' => $trend === null,
        ]);
    }

    // FR-03 Funnel (I3): viewed → cart → booking → purchase (purchase =
    // dibayar, keputusan 2026-11-08). Angka memakai facts yang sama
    // dengan FR-01/FR-02, jadi konsisten dengan dashboard dan tren.
    public function funnel(AnalyticsPeriodRequest $request): View
    {
        [$dari, $sampai] = $request->period();

        $payload = $this->facts->build($dari, $sampai);

        $funnel = $this->client->funnel($payload);

        return view('admin.analytics-funnel', [
            'dari' => $dari,
            'sampai' => $sampai,
            'funnel' => $funnel,
            'unavailable' => $funnel === null,
            'has_events' => $funnel !== null
                && array_sum(array_column($funnel['stages'] ?? [], 'count')) > 0,
            'chart' => $funnel === null ? null : $this->funnelChart($funnel),
        ]);
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
        $buckets = $summary['buckets'] ?? [];

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
}
