<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Simulation\PeriodResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// M5 facts builder (FR-01..06). Builds the "facts" payload the Python
// analytics service expects (blueprint §11.3, checklist P5 §C):
// one row per (WITA day, product), plus the product/category lists and the
// optional order_totals block. No personal data anywhere: facts carry only
// a date, a product id, and counts; products carry id/name/category/price;
// account ids, names, and emails are never selected.
//
// Sources (keputusan 2026-11-08):
//   - Funnel stages (views, add_to_carts, bookings, purchases, cancels)
//     come from the `events` table — one row per recorded event. Views in
//     facts are already deduped: ActivityRecorder enforces the 30-minute
//     dedup when the event is written (EVT-02); the builder counts events
//     as stored and never re-dedups (simulation orphan views are planned
//     counts and must stay intact).
//   - Sales (units_sold, revenue_idr, orders_paid) come from PAID order
//     items only (orders.status = 'dibayar', bucketed by orders.paid_at in
//     WITA). "Purchase dihitung saat dibayar" — the purchase event is
//     written at the same payment instant, so the two sources agree on
//     well-formed data (invarian I1: purchase events = paid items).
//   - order_totals counts UNIQUE paid orders per WITA day (never the sum
//     of orders_paid per product — that double-counts multi-product
//     orders, I5). by_category may exceed the day total (one order can
//     hold two categories); that is expected, not an error.
// Dates are WITA calendar dates; storage is UTC (PeriodResolver maps the
// WITA range to UTC instants, M4 precedent). Periods longer than 366 days
// are rejected, as are start > end and future end dates.
class AnalyticsFactsBuilder
{
    public const MAX_PERIOD_DAYS = 366;

    public const ORIGINS = ['normal', 'developer_simulation'];

    public function __construct(
        private string $timezone = 'Asia/Makassar',
    ) {}

    public static function fromConfig(): self
    {
        return new self((string) config('ira.reporting_timezone', 'Asia/Makassar'));
    }

    /**
     * Full base payload for one analytics call: period + origin_filter +
     * facts + products + categories + order_totals. Pass it straight to
     * AnalyticsClient::summary()/trend()/funnel()/interest()/category(),
     * adding endpoint-specific options (metric, product_ids, ...) as needed.
     *
     * @param string $startDate Y-m-d (WITA, inclusive)
     * @param string $endDate Y-m-d (WITA, inclusive)
     * @param string|null $origin null = all origins; otherwise 'normal' or 'developer_simulation'
     */
    public function build(string $startDate, string $endDate, ?string $origin = null): array
    {
        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'origin_filter' => $origin ?? 'all',
            'facts' => $this->buildFacts($startDate, $endDate, $origin),
            'products' => $this->buildProducts(),
            'categories' => $this->buildCategories(),
            'order_totals' => $this->buildOrderTotals($startDate, $endDate, $origin),
        ];
    }

    /**
     * Facts rows: [{date, product_id, units_sold, revenue_idr, orders_paid,
     * views, add_to_carts, bookings, purchases, cancels}]. Only rows with
     * at least one non-zero field are included; sorted by date, product.
     */
    public function buildFacts(string $startDate, string $endDate, ?string $origin = null): array
    {
        $this->validateOrigin($origin);
        $range = $this->resolve($startDate, $endDate);

        // [date][product_id] => fields (+ temporary '_orders' set).
        $rows = [];

        $events = DB::table('events')
            ->when($origin !== null, fn ($q) => $q->where('origin', $origin))
            ->whereBetween('occurred_at', [$range->startUtc, $range->endUtc])
            ->select('event_type', 'product_id', 'occurred_at')
            ->get();

        $eventField = [
            'view' => 'views',
            'add_to_cart' => 'add_to_carts',
            'booking' => 'bookings',
            'purchase' => 'purchases',
            'cancel' => 'cancels',
        ];

        foreach ($events as $event) {
            $field = $eventField[$event->event_type] ?? null;

            if ($field === null) {
                continue;
            }

            $day = $this->witaDate($event->occurred_at);
            $cell = &$this->cell($rows, $day, (int) $event->product_id);
            $cell[$field]++;
        }

        $items = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'dibayar')
            ->whereBetween('orders.paid_at', [$range->startUtc, $range->endUtc])
            ->when($origin !== null, fn ($q) => $q->where('orders.origin', $origin)->where('order_items.origin', $origin))
            ->select(
                'order_items.order_id',
                'order_items.product_id',
                'order_items.quantity',
                'order_items.subtotal_idr',
                'orders.paid_at',
            )
            ->get();

        foreach ($items as $item) {
            $day = $this->witaDate($item->paid_at);
            $cell = &$this->cell($rows, $day, (int) $item->product_id);
            $cell['units_sold'] += (int) $item->quantity;
            $cell['revenue_idr'] += (int) $item->subtotal_idr;
            $cell['_orders'][(int) $item->order_id] = true;
        }

        unset($cell);

        $facts = [];

        foreach ($rows as $day => $byProduct) {
            foreach ($byProduct as $productId => $cell) {
                $cell['orders_paid'] = count($cell['_orders']);
                unset($cell['_orders']);

                if (array_sum($cell) === 0) {
                    continue;
                }

                $facts[] = array_merge(
                    ['date' => $day, 'product_id' => $productId],
                    $cell,
                );
            }
        }

        usort($facts, fn ($a, $b) => [$a['date'], $a['product_id']] <=> [$b['date'], $b['product_id']]);

        return $facts;
    }

    /**
     * Product list for the request: every product (active and inactive —
     * the module labels inactive ones itself), with active_since = the
     * product's creation date in WITA.
     */
    public function buildProducts(): array
    {
        return Product::query()
            ->orderBy('id')
            ->get(['id', 'name', 'category_id', 'price_idr', 'is_active', 'created_at'])
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price_idr' => $product->price_idr,
                'is_active' => (bool) $product->is_active,
                'active_since' => $product->created_at
                    ? $product->created_at->copy()->setTimezone($this->timezone)->toDateString()
                    : null,
            ])
            ->all();
    }

    /** Category list for the request: [{id, name}], ordered by id. */
    public function buildCategories(): array
    {
        return Category::query()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
            ->all();
    }

    /**
     * order_totals rows: [{date, orders_paid, by_category:
     * [{category_id, orders_paid}]}]. Days without paid orders are
     * omitted (the module treats a missing day as 0). by_category is
     * present on every row (possibly empty) so the module's whole-period
     * by_category stays defined.
     */
    public function buildOrderTotals(string $startDate, string $endDate, ?string $origin = null): array
    {
        $this->validateOrigin($origin);
        $range = $this->resolve($startDate, $endDate);

        $orders = DB::table('orders')
            ->where('status', 'dibayar')
            ->whereBetween('paid_at', [$range->startUtc, $range->endUtc])
            ->when($origin !== null, fn ($q) => $q->where('origin', $origin))
            ->select('id', 'paid_at')
            ->get();

        // date => [order_id => true]
        $byDay = [];

        foreach ($orders as $order) {
            $byDay[$this->witaDate($order->paid_at)][$order->id] = true;
        }

        if ($byDay === []) {
            return [];
        }

        // NB: array_merge() menomori ulang kunci integer, jadi order_id
        // akan hilang bila $byDay digabung langsung. Ambil kuncinya
        // dulu per hari — di sini order_id adalah nilai, bukan kunci.
        $orderIds = [];

        foreach ($byDay as $dayIds) {
            $orderIds = array_merge($orderIds, array_keys($dayIds));
        }

        // (order_id, category_id) pairs, deduplicated: one order counts
        // once per category even with two products of that category.
        $itemCategories = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $orderIds)
            ->select('order_items.order_id', 'products.category_id')
            ->distinct()
            ->get();

        $orderCategories = [];

        foreach ($itemCategories as $row) {
            if ($row->category_id === null) {
                continue;
            }

            $orderCategories[(int) $row->order_id][(int) $row->category_id] = true;
        }

        $totals = [];

        foreach ($byDay as $day => $ids) {
            $byCategory = [];

            foreach (array_keys($ids) as $orderId) {
                foreach (array_keys($orderCategories[$orderId] ?? []) as $categoryId) {
                    $byCategory[$categoryId] = ($byCategory[$categoryId] ?? 0) + 1;
                }
            }

            ksort($byCategory);

            $totals[] = [
                'date' => $day,
                'orders_paid' => count($ids),
                'by_category' => array_map(
                    fn ($categoryId, $count) => ['category_id' => $categoryId, 'orders_paid' => $count],
                    array_keys($byCategory),
                    array_values($byCategory),
                ),
            ];
        }

        usort($totals, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $totals;
    }

    private function resolve(string $startDate, string $endDate): \App\Simulation\PeriodRange
    {
        return (new PeriodResolver($this->timezone, self::MAX_PERIOD_DAYS))
            ->resolve($startDate, $endDate);
    }

    private function validateOrigin(?string $origin): void
    {
        if ($origin !== null && ! in_array($origin, self::ORIGINS, true)) {
            throw new InvalidArgumentException(
                "origin harus null atau salah satu dari: ".implode(', ', self::ORIGINS).".",
            );
        }
    }

    /** WITA calendar date (Y-m-d) of a stored UTC datetime value. */
    private function witaDate(mixed $value): string
    {
        return Carbon::parse($value, 'UTC')->setTimezone($this->timezone)->toDateString();
    }

    /** @return array Reference to the fact cell for (date, product). */
    private function &cell(array &$rows, string $day, int $productId): array
    {
        if (! isset($rows[$day][$productId])) {
            $rows[$day][$productId] = [
                'units_sold' => 0,
                'revenue_idr' => 0,
                'orders_paid' => 0,
                'views' => 0,
                'add_to_carts' => 0,
                'bookings' => 0,
                'purchases' => 0,
                'cancels' => 0,
                '_orders' => [],
            ];
        }

        return $rows[$day][$productId];
    }
}
