<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

// M5 analytics client (FR-01..06). POSTs JSON payloads to the internal
// Python analytics service (iraweb/python/analytics/server.py) and returns
// the structured result as an array.
//
// Three states, kept apart on purpose (rencana M5 §4):
//   - success          -> the result array;
//   - service down     -> NULL ("tidak tersedia"). The caller shows
//     "Perhitungan sedang tidak tersedia" + retry; it must NOT surface
//     an exception to the page and must NOT treat it as zero;
//   - validation/other -> an exception with a clear Indonesian message
//     (422 = bad payload, 401 = wrong/missing key, anything else = the
//     HTTP status). These are caller/programming errors, not "no data".
// The internal key is never hardcoded: it comes from config('analytics.key')
// (env ANALYTICS_INTERNAL_KEY). A missing key fails fast with an actionable
// message instead of sending an unauthenticated request.
//
// Each endpoint only accepts its own keys (server.py ENDPOINTS table — the
// server rejects unknown keys with 422). AnalyticsFactsBuilder::build()
// returns the FULL payload (facts + products + categories + order_totals),
// so post() filters it per endpoint before sending: /v1/summary takes
// order_totals but not categories; /v1/trend and /v1/funnel take neither.
// The map below MUST stay identical to the server's ENDPOINTS table.
class AnalyticsClient
{
    /** @var array<string, list<string>> endpoint -> body keys the server accepts. */
    private const ALLOWED_KEYS = [
        'summary' => [
            'facts', 'products', 'period', 'origin_filter', 'order_totals',
            'previous_facts', 'previous_order_totals', 'granularity', 'top_n',
            'alpha', 'auto_day_max', 'auto_week_max', 'baseline',
        ],
        'trend' => [
            'facts', 'products', 'period', 'origin_filter', 'metric',
            'product_ids', 'n_min_weeks', 'min_avg_per_week', 'alpha',
            'r_min', 'q_strong',
        ],
        'funnel' => [
            'facts', 'products', 'period', 'origin_filter', 'product_ids',
            'category_id',
        ],
        'compare' => [
            'facts_a', 'facts_b', 'products', 'period_a', 'period_b',
            'origin_filter', 'order_totals_a', 'order_totals_b', 'top_n',
            'alpha', 'baseline',
        ],
        'interest' => [
            'facts', 'products', 'period', 'origin_filter', 'category_id',
            'min_views', 'max_ratio', 'max_upper', 'comparator_min_ratio',
            'comparator_min_purchases', 'comparator_max', 'confidence',
        ],
        'category' => [
            'facts', 'products', 'period', 'origin_filter', 'categories',
            'order_totals', 'metric', 'granularity', 'price_bins',
            'n_min_weeks', 'min_avg_per_week', 'alpha', 'r_min', 'q_strong',
        ],
        // M7 ANL-63: kunci HARUS identik dengan tabel ENDPOINTS server
        // untuk /v1/category-compare (tanpa order_totals_* — fungsi
        // compare_categories tidak menerimanya).
        'category-compare' => [
            'facts_a', 'facts_b', 'products', 'period_a', 'period_b',
            'origin_filter', 'categories', 'baseline',
        ],
        'insight' => [
            'summary', 'funnel', 'trend', 'interest', 'compare',
            'origin_transactions', 'period', 'max_items', 'alpha',
        ],
    ];

    public function __construct(
        private ?string $baseUrl = null,
        private ?string $key = null,
        private ?int $timeout = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('analytics.url', 'http://127.0.0.1:8001'), '/');
        $this->key = $key ?? config('analytics.key');
        $this->timeout = $timeout ?? (int) config('analytics.timeout', 10);
    }

    /** FR-01 dashboard summary. Payload: AnalyticsFactsBuilder::build(). */
    public function summary(array $payload): ?array
    {
        return $this->post('summary', $payload);
    }

    /** FR-02 trend classification. Payload: build() + optional metric/product_ids. */
    public function trend(array $payload): ?array
    {
        return $this->post('trend', $payload);
    }

    /** FR-03 funnel. Payload: build() + optional product_ids/category_id. */
    public function funnel(array $payload): ?array
    {
        return $this->post('funnel', $payload);
    }

    /** FR-04 period comparison. Payload: facts_a/facts_b + period_a/period_b. */
    public function compare(array $payload): ?array
    {
        return $this->post('compare', $payload);
    }

    /** FR-05 activity/interest. Payload: build() + optional category_id. */
    public function interest(array $payload): ?array
    {
        return $this->post('interest', $payload);
    }

    /** FR-06 categories. Payload: build() (categories included). */
    public function category(array $payload): ?array
    {
        return $this->post('category', $payload);
    }

    /** FR-06 ANL-63 category matrix across two periods. Payload: facts_a/facts_b + period_a/period_b + categories. */
    public function categoryCompare(array $payload): ?array
    {
        return $this->post('category-compare', $payload);
    }

    /** Insight INS-01..06, from already-computed results. */
    public function insight(array $payload): ?array
    {
        return $this->post('insight', $payload);
    }

    private function post(string $endpoint, array $payload): ?array
    {
        if ($this->key === null || $this->key === '') {
            throw new InvalidArgumentException(
                'ANALYTICS_INTERNAL_KEY belum diisi: isi di .env (nilai lokal, '
                .'jangan di-commit), lalu restart aplikasi.',
            );
        }

        // Only the keys this endpoint accepts (see ALLOWED_KEYS above) —
        // anything else makes the server answer 422 "kunci tak dikenal".
        $payload = array_intersect_key($payload, array_flip(self::ALLOWED_KEYS[$endpoint]));

        try {
            $response = Http::asJson()
                ->withHeaders(['X-Internal-Key' => $this->key])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/v1/{$endpoint}", $payload);
        } catch (ConnectionException $e) {
            // Unreachable / refused / timed out: "tidak tersedia", not an
            // exception and not zero. Log the endpoint only — never the
            // key, never the payload.
            Log::warning("Layanan analitik tidak tersedia ({$endpoint}): {$e->getMessage()}");

            return null;
        }

        if ($response->successful()) {
            $data = $response->json();

            if (! is_array($data)) {
                throw new RuntimeException(
                    "Layanan analitik ({$endpoint}) mengembalikan respons yang bukan JSON object.",
                );
            }

            return $data;
        }

        $message = $response->json('message') ?? $response->body();
        $message = mb_substr((string) $message, 0, 500);

        if ($response->status() === 422) {
            throw new InvalidArgumentException(
                "Data analitik tidak valid ({$endpoint}): {$message}",
            );
        }

        if ($response->status() === 401) {
            throw new RuntimeException(
                "Kunci internal analitik ditolak ({$endpoint}): periksa ANALYTICS_INTERNAL_KEY di .env.",
            );
        }

        throw new RuntimeException(
            "Layanan analitik gagal ({$endpoint}): HTTP {$response->status()}. {$message}",
        );
    }
}
