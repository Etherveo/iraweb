<?php

namespace App\Simulation;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

// M4 simulator (SIM-11..13, SIM-16..24, SIM-29..31, blueprint §9.5/§9.6).
// Pure function of (params, seed): same params + same seed + same sorted
// inputs → identical plan (SIM-05). Builds no DB rows, reads no config
// and never calls now() for a plan decision; the caller
// (SimulationRequestValidator) resolves users/products from the database
// and config defaults before calling.
//
// Fixed RNG stream order inside one plan call (changing it would change
// plans for the same seed, so it is frozen): booking times → users →
// final statuses → per-order items → funnel chain → payment method →
// orphan events.
//
// Planned-order shape:
// [
//   'user_id' => int, 'items' => [['product_id'=>int, 'quantity'=>int,
//     'unit_price_idr'=>int, 'subtotal_idr'=>int]], 'total_idr' => int,
//   'payment_method' => string (code, e.g. 'qris'),
//   'final_status' => 'selesai'|'dibatalkan'|'kedaluwarsa',
//   'payment_status' => 'paid'|'cancelled'|'expired',
//   'booking_at' => CarbonImmutable (UTC), 'view_at' => ..., 'add_to_cart_at' => ...,
//   'paid_at' => CarbonImmutable|null, 'cancelled_at' => CarbonImmutable|null,
//   'payment_deadline_at' => CarbonImmutable (UTC),
// ]
final class FunnelPlanner
{
    /** Default lag ranges in minutes, mirroring config/simulation.php. */
    public const DEFAULT_LAG_MINUTES = [
        'view_to_cart' => [1, 30],
        'cart_to_booking' => [1, 20],
        'booking_to_paid' => [1, 10],
    ];

    public const DEFAULT_PAYMENT_EXPIRY_MINUTES = 30;

    public const DEFAULT_ITEMS_PER_TX = [1, 3];

    public const DEFAULT_QTY_PER_ITEM = [1, 99];

    /** Final order status → payment status (blueprint §9.7). */
    public const PAYMENT_STATUSES = [
        'selesai' => 'paid',
        'dibatalkan' => 'cancelled',
        'kedaluwarsa' => 'expired',
    ];

    /**
     * Build a transaction plan (Aksi 2 user-first / Aksi 3 period-first).
     *
     * Params (required unless noted; defaults mirror config/simulation.php):
     * - action: 'tx_user_first' | 'tx_period_first'
     * - user_ids: list<int> (sorted defensively), ≥ 1
     * - products: list<array{id:int, price_idr:int}> (sorted by id), ≥ 1
     * - period_start, period_end: 'Y-m-d' (WITA)
     * - transaction_count: int ≥ 1
     * - items_per_tx: [min, max] (default [1, 3])
     * - qty_per_item: [min, max] (default [1, 99])
     * - time_distribution: 'random' | 'even' | 'single' (default 'random')
     * - single_point: CarbonImmutable (UTC) or ISO string — required for 'single'
     * - user_distribution: 'random' | 'even' | 'weighted' (default 'random')
     * - product_distribution: 'random' | 'even' | 'weighted' (default 'random')
     * - status_mix: array<string,int> summing to 100
     *   (default config status_mix: selesai 80 / dibatalkan 10 / kedaluwarsa 10)
     * - funnel_view_to_cart, funnel_cart_to_booking: int 1..100 (default 40 / 60)
     * - lag_minutes: ['view_to_cart'=>[min,max], 'cart_to_booking'=>[...],
     *   'booking_to_paid'=>[...]] (default DEFAULT_LAG_MINUTES)
     * - payment_methods: list<string> non-empty (active codes)
     * - payment_expiry_minutes: int ≥ 1 (default 30)
     * - max_period_days: int ≥ 1 (default 366)
     */
    public function plan(array $params, int $seed): SimulationPlan
    {
        $params = $this->normalizeTx($params, $seed);
        $period = (new PeriodResolver($params['timezone'], $params['max_period_days']))
            ->resolve($params['period_start'], $params['period_end']);

        $random = new SeededRandom($seed);
        $count = $params['transaction_count'];

        $times = $this->bookingTimes($random, $params, $period, $count);
        $userIds = (new UserDistributor($random))->distribute($count, $params['user_ids'], $params['user_distribution']);
        $statuses = (new StatusAssigner($random))->assign($count, $params['status_mix']);
        $productPicker = new ProductPicker($random);

        $orders = [];

        foreach (range(0, $count - 1) as $i) {
            $bookingAt = $times[$i];
            $itemCount = $random->nextInt($params['items_per_tx'][0], $params['items_per_tx'][1]);
            $picked = $productPicker->pickDistinct($itemCount, $params['products'], $params['product_distribution']);

            $items = [];
            $total = 0;

            foreach ($picked as $product) {
                $qty = $random->nextInt($params['qty_per_item'][0], $params['qty_per_item'][1]);
                $subtotal = $product['price_idr'] * $qty;
                $items[] = [
                    'product_id' => $product['id'],
                    'quantity' => $qty,
                    'unit_price_idr' => $product['price_idr'],
                    'subtotal_idr' => $subtotal,
                ];
                $total += $subtotal;
            }

            $orders[] = $this->buildOrder(
                $random, $userIds[$i], $items, $total, $statuses[$i],
                $bookingAt, $period, $params,
            );
        }

        return $this->finishPlan($params, $seed, $period, $orders, $random);
    }

    /**
     * Build an explicit-funnel plan (Aksi 4, SIM-31, blueprint §9.8).
     *
     * Same common params as plan(), plus:
     * - view_count, add_to_cart_count, booking_count, purchase_count: ints,
     *   view ≥ add_to_cart ≥ booking ≥ purchase ≥ 0 (SIM-31).
     * - max_events_per_request: int ≥ 1 (default 2000); total generated
     *   events (view + add_to_cart + 2×booking) must not exceed it.
     *
     * Each booking is a single-item order (qty 1), so the requested event
     * counts are hit exactly: attached views/carts equal booking_count,
     * and the orphans make up the rest. Non-purchase bookings split
     * 50/50 dibatalkan/kedaluwarsa (rencana §4.10 D2).
     */
    public function planFunnel(array $params, int $seed): SimulationPlan
    {
        $params = $this->normalizeFunnel($params, $seed);
        $period = (new PeriodResolver($params['timezone'], $params['max_period_days']))
            ->resolve($params['period_start'], $params['period_end']);

        $random = new SeededRandom($seed);
        $count = $params['booking_count'];

        $times = $this->bookingTimes($random, $params, $period, $count);
        $userIds = (new UserDistributor($random))->distribute($count, $params['user_ids'], $params['user_distribution']);
        $statuses = $this->funnelStatuses($random, $params);
        $productPicker = new ProductPicker($random);

        $orders = [];

        foreach (range(0, $count - 1) as $i) {
            $picked = $productPicker->pickDistinct(1, $params['products'], $params['product_distribution']);
            $product = $picked[0];

            $items = [[
                'product_id' => $product['id'],
                'quantity' => 1,
                'unit_price_idr' => $product['price_idr'],
                'subtotal_idr' => $product['price_idr'],
            ]];

            $orders[] = $this->buildOrder(
                $random, $userIds[$i], $items, $product['price_idr'], $statuses[$i],
                $times[$i], $period, $params,
            );
        }

        return $this->finishFunnelPlan($params, $seed, $period, $orders, $random);
    }

    /**
     * Booking instants. Blueprint §9.5: for 'random'/'even' the booking
     * is sampled from [start + max pre-lag, end − max post-lag] so the
     * whole funnel chain lands inside the period. For 'single' the
     * requested point may sit anywhere in the period; buildChain() then
     * compresses the lags so the chain still fits (SIM-29).
     *
     * @return list<CarbonImmutable>
     */
    private function bookingTimes(SeededRandom $random, array $params, PeriodRange $period, int $count): array
    {
        $distributor = new TimeDistributor($random);

        if ($params['time_distribution'] === 'single') {
            return $distributor->distribute(
                $count, $period->startUtc, $period->endUtc, 'single', $params['single_point'],
            );
        }

        $maxPreLag = $params['lag_minutes']['view_to_cart'][1] + $params['lag_minutes']['cart_to_booking'][1];
        $maxPostLag = max($params['lag_minutes']['booking_to_paid'][1], $params['payment_expiry_minutes']);

        $windowStart = $period->startUtc->addMinutes($maxPreLag);
        $windowEnd = $period->endUtc->subMinutes($maxPostLag);

        if ($windowEnd < $windowStart) {
            throw new InvalidArgumentException('Periode terlalu pendek untuk rantai funnel (butuh ruang untuk jeda sebelum dan sesudah booking).');
        }

        return $distributor->distribute(
            $count, $windowStart, $windowEnd, $params['time_distribution'],
        );
    }

    /** @param list<array{product_id:int, quantity:int, unit_price_idr:int, subtotal_idr:int}> $items */
    private function buildOrder(
        SeededRandom $random,
        int $userId,
        array $items,
        int $total,
        string $finalStatus,
        CarbonImmutable $bookingAt,
        PeriodRange $period,
        array $params,
    ): array {
        $chain = $this->buildChain($random, $bookingAt, $period, $finalStatus, $params);

        return [
            'user_id' => $userId,
            'items' => $items,
            'total_idr' => $total,
            'payment_method' => $random->pick($params['payment_methods']),
            'final_status' => $finalStatus,
            'payment_status' => self::PAYMENT_STATUSES[$finalStatus],
            'booking_at' => $bookingAt,
            'view_at' => $chain['view_at'],
            'add_to_cart_at' => $chain['add_to_cart_at'],
            'paid_at' => $chain['paid_at'],
            'cancelled_at' => $chain['cancelled_at'],
            'payment_deadline_at' => $chain['payment_deadline_at'],
        ];
    }

    /**
     * Funnel chain for one order (blueprint §9.5, SIM-29):
     * view ≤ add_to_cart ≤ booking ≤ paid_at/cancelled_at, and
     * paid_at ≤ payment_deadline_at. Lags are seeded draws from
     * lag_minutes. All instants are clamped into the period: for
     * random/even bookings the window already guarantees this, so
     * clamping only bites for a 'single' point near a period edge —
     * there the lags compress toward 0 and the deadline caps at the
     * period end (the effective payment window shrinks).
     *
     * - selesai: paid_at = booking + booking→paid lag.
     * - dibatalkan: cancelled_at = booking + booking→paid lag (buyer
     *   cancels shortly after booking; no separate lag is specified
     *   anywhere, so the paid lag range is reused — see report).
     * - kedaluwarsa: cancelled_at = payment deadline (PAY-32/34: the
     *   order expires exactly when payment expires).
     *
     * @return array{view_at: CarbonImmutable, add_to_cart_at: CarbonImmutable, paid_at: ?CarbonImmutable, cancelled_at: ?CarbonImmutable, payment_deadline_at: CarbonImmutable}
     */
    private function buildChain(
        SeededRandom $random,
        CarbonImmutable $bookingAt,
        PeriodRange $period,
        string $finalStatus,
        array $params,
    ): array {
        $lagVC = $random->nextInt($params['lag_minutes']['view_to_cart'][0], $params['lag_minutes']['view_to_cart'][1]);
        $lagCB = $random->nextInt($params['lag_minutes']['cart_to_booking'][0], $params['lag_minutes']['cart_to_booking'][1]);
        $lagBP = $random->nextInt($params['lag_minutes']['booking_to_paid'][0], $params['lag_minutes']['booking_to_paid'][1]);

        // Pre-chain, compressed when the booking sits near the start.
        $availablePre = max(0, $bookingAt->getTimestamp() - $period->startUtc->getTimestamp());
        $lagCBSeconds = min($lagCB * 60, $availablePre);
        $lagVCSeconds = min($lagVC * 60, $availablePre - $lagCBSeconds);

        $viewAt = $bookingAt->subSeconds($lagVCSeconds + $lagCBSeconds);
        $cartAt = $bookingAt->subSeconds($lagCBSeconds);

        $deadline = $bookingAt->addMinutes($params['payment_expiry_minutes']);

        $paidAt = null;
        $cancelledAt = null;

        if ($finalStatus === 'selesai') {
            $paidAt = $bookingAt->addMinutes($lagBP);
        } elseif ($finalStatus === 'dibatalkan') {
            $cancelledAt = $bookingAt->addMinutes($lagBP);
        } else {
            $cancelledAt = $deadline;
        }

        // Post-chain clamp (single mode near the period end only).
        if ($paidAt !== null && $paidAt > $period->endUtc) {
            $paidAt = $period->endUtc;
        }

        if ($cancelledAt !== null && $cancelledAt > $period->endUtc) {
            $cancelledAt = $period->endUtc;
        }

        if ($deadline > $period->endUtc) {
            $deadline = $period->endUtc;
        }

        return [
            'view_at' => $viewAt,
            'add_to_cart_at' => $cartAt,
            'paid_at' => $paidAt,
            'cancelled_at' => $cancelledAt,
            'payment_deadline_at' => $deadline,
        ];
    }

    /**
     * Orphan events (blueprint §9.5): add_to_cart without booking and
     * view without add_to_cart, for seeded (user, product) pairs inside
     * the same period. Counts follow the funnel ratios:
     * carts_total = ceil(items / cart_ratio), views_total =
     * ceil(carts_total / view_ratio); orphans are the totals minus the
     * chain-attached events (one view + one cart per order item).
     *
     * @return list<array{type: string, user_id: int, product_id: int, occurred_at: CarbonImmutable}>
     */
    private function buildOrphans(
        SeededRandom $random,
        int $orphanCarts,
        int $orphanViews,
        array $userIds,
        array $products,
        PeriodRange $period,
    ): array {
        $total = $orphanCarts + $orphanViews;

        if ($total === 0) {
            return [];
        }

        $times = (new TimeDistributor($random))->distribute(
            $total, $period->startUtc, $period->endUtc, 'random',
        );

        $orphans = [];

        for ($i = 0; $i < $total; $i++) {
            $orphans[] = [
                'type' => $i < $orphanCarts ? 'add_to_cart' : 'view',
                'user_id' => $random->pick($userIds),
                'product_id' => $random->pick($products)['id'],
                'occurred_at' => $times[$i],
            ];
        }

        return $orphans;
    }

    private function finishPlan(array $params, int $seed, PeriodRange $period, array $orders, SeededRandom $random): SimulationPlan
    {
        $totalItems = $this->totalItems($orders);

        $cartsTotal = (int) ceil($totalItems / ($params['funnel_cart_to_booking'] / 100));
        $viewsTotal = (int) ceil($cartsTotal / ($params['funnel_view_to_cart'] / 100));

        $orphans = $this->buildOrphans(
            $random,
            $cartsTotal - $totalItems,
            $viewsTotal - $cartsTotal,
            $params['user_ids'],
            $params['products'],
            $period,
        );

        return new SimulationPlan(
            $params['action'],
            $params,
            SimulationPlan::hashParams($params),
            $seed,
            $orders,
            $orphans,
            $this->estimateRows($orders, $orphans),
            $period,
        );
    }

    private function finishFunnelPlan(array $params, int $seed, PeriodRange $period, array $orders, SeededRandom $random): SimulationPlan
    {
        // One item per booking, so attached views/carts equal booking_count.
        $orphans = $this->buildOrphans(
            $random,
            $params['add_to_cart_count'] - $params['booking_count'],
            $params['view_count'] - $params['booking_count'],
            $params['user_ids'],
            $params['products'],
            $period,
        );

        return new SimulationPlan(
            $params['action'],
            $params,
            SimulationPlan::hashParams($params),
            $seed,
            $orders,
            $orphans,
            $this->estimateRows($orders, $orphans),
            $period,
        );
    }

    /** @return list<string> */
    private function funnelStatuses(SeededRandom $random, array $params): array
    {
        $purchase = $params['purchase_count'];
        $rest = $params['booking_count'] - $purchase;

        $statuses = array_fill(0, $purchase, 'selesai');

        if ($rest > 0) {
            // D2: the non-purchase remainder splits 50/50 (seeded).
            $statuses = array_merge(
                $statuses,
                (new StatusAssigner($random))->assign($rest, ['dibatalkan' => 50, 'kedaluwarsa' => 50]),
            );
        }

        return $random->shuffle($statuses);
    }

    /** @param list<array> $orders */
    private function totalItems(array $orders): int
    {
        $total = 0;

        foreach ($orders as $order) {
            $total += count($order['items']);
        }

        return $total;
    }

    /**
     * Row estimates for the preview (SIM-25). Formulae, per order:
     * - events: 4 per item (view + add_to_cart + booking + purchase/cancel)
     *   + orphan events.
     * - order_status_histories: 1 initial row (recordInitial) + 3
     *   transitions for 'selesai', 1 for 'dibatalkan'/'kedaluwarsa'.
     * - payment_status_transitions: 2 per payment (recordInitial
     *   null→pending + pending→final).
     *
     * @param  list<array> $orders
     * @param  list<array> $orphans
     * @return array<string,int>
     */
    private function estimateRows(array $orders, array $orphans): array
    {
        $totalItems = $this->totalItems($orders);
        $histories = 0;

        foreach ($orders as $order) {
            $histories += 1 + ($order['final_status'] === 'selesai' ? 3 : 1);
        }

        return [
            'orders' => count($orders),
            'order_items' => $totalItems,
            'payments' => count($orders),
            'events' => ($totalItems * 4) + count($orphans),
            'order_status_histories' => $histories,
            'payment_status_transitions' => count($orders) * 2,
        ];
    }

    // ---- Normalization -------------------------------------------------

    /** @return array<string,mixed> */
    private function normalizeTx(array $params, int $seed): array
    {
        $params = $this->normalizeCommon($params, $seed);

        if (! in_array($params['action'], ['tx_user_first', 'tx_period_first'], true)) {
            throw new InvalidArgumentException("Aksi tidak dikenal untuk rencana transaksi: '{$params['action']}'.");
        }

        $count = $params['transaction_count'] ?? null;

        if (! is_int($count) || $count < 1) {
            throw new InvalidArgumentException('Jumlah transaksi harus bilangan bulat ≥ 1.');
        }

        $params['transaction_count'] = $count;
        $params['items_per_tx'] = $this->normalizeRange($params['items_per_tx'] ?? self::DEFAULT_ITEMS_PER_TX, 'items_per_tx', 1, null);
        $params['qty_per_item'] = $this->normalizeRange($params['qty_per_item'] ?? self::DEFAULT_QTY_PER_ITEM, 'qty_per_item', 1, 99);

        return $params;
    }

    /** @return array<string,mixed> */
    private function normalizeFunnel(array $params, int $seed): array
    {
        $params = $this->normalizeCommon($params, $seed);

        if (($params['action'] ?? null) !== 'funnel_events') {
            throw new InvalidArgumentException("Aksi tidak dikenal untuk rencana funnel: '".($params['action'] ?? '')."'.");
        }

        $counts = [];

        foreach (['view_count', 'add_to_cart_count', 'booking_count', 'purchase_count'] as $key) {
            $value = $params[$key] ?? null;

            if (! is_int($value) || $value < 0) {
                throw new InvalidArgumentException("'{$key}' harus bilangan bulat ≥ 0.");
            }

            $counts[$key] = $value;
        }

        if (! ($counts['view_count'] >= $counts['add_to_cart_count']
            && $counts['add_to_cart_count'] >= $counts['booking_count']
            && $counts['booking_count'] >= $counts['purchase_count'])) {
            throw new InvalidArgumentException('Jumlah funnel harus menyusut: view ≥ add_to_cart ≥ booking ≥ purchase ≥ 0 (SIM-31).');
        }

        if ($counts['booking_count'] < 1) {
            throw new InvalidArgumentException("Aksi funnel membutuhkan minimal 1 booking.");
        }

        // Total generated event rows: requested views + carts + 2 per
        // booking (its chain view/cart are already inside the requested
        // counts, but booking + purchase/cancel are extra rows).
        $totalEvents = $counts['view_count'] + $counts['add_to_cart_count'] + ($counts['booking_count'] * 2);
        $maxEvents = $params['max_events_per_request'] ?? 2000;

        if (! is_int($maxEvents) || $maxEvents < 1) {
            throw new InvalidArgumentException("'max_events_per_request' harus bilangan bulat ≥ 1.");
        }

        if ($totalEvents > $maxEvents) {
            throw new InvalidArgumentException("Total event funnel {$totalEvents} melebihi batas {$maxEvents} per request (SIM-31).");
        }

        return array_merge($params, $counts, ['max_events_per_request' => $maxEvents]);
    }

    /**
     * Shared normalization: defaults, sorting of inputs (blueprint §9.2),
     * distribution/mix/ratio validation, seed stored in params.
     *
     * @return array<string,mixed>
     */
    private function normalizeCommon(array $params, int $seed): array
    {
        $params['seed'] = $seed;

        // Reporting timezone for the period (WITA by config); threaded
        // through params so the planner never reads config itself.
        $params['timezone'] = (string) ($params['timezone'] ?? 'Asia/Makassar');

        $userIds = $params['user_ids'] ?? [];

        if (! is_array($userIds) || $userIds === []) {
            throw new InvalidArgumentException('Daftar akun tidak boleh kosong.');
        }

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        sort($userIds);
        $params['user_ids'] = $userIds;

        $products = $params['products'] ?? [];

        if (! is_array($products) || $products === []) {
            throw new InvalidArgumentException('Daftar produk tidak boleh kosong.');
        }

        $normalized = [];

        foreach ($products as $product) {
            if (! is_array($product) || ! isset($product['id'], $product['price_idr'])) {
                throw new InvalidArgumentException("Setiap produk harus memiliki 'id' dan 'price_idr'.");
            }

            $normalized[] = ['id' => (int) $product['id'], 'price_idr' => (int) $product['price_idr']];
        }

        usort($normalized, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        $ids = array_column($normalized, 'id');

        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Daftar produk mengandung id ganda.');
        }

        $params['products'] = $normalized;

        foreach (['period_start', 'period_end'] as $key) {
            if (! is_string($params[$key] ?? null) || $params[$key] === '') {
                throw new InvalidArgumentException("'{$key}' harus tanggal 'Y-m-d'.");
            }
        }

        $params['action'] = (string) ($params['action'] ?? '');
        $params['time_distribution'] = $this->normalizeEnum($params['time_distribution'] ?? 'random', ['random', 'even', 'single'], 'time_distribution');
        $params['user_distribution'] = $this->normalizeEnum($params['user_distribution'] ?? 'random', ['random', 'even', 'weighted'], 'user_distribution');
        $params['product_distribution'] = $this->normalizeEnum($params['product_distribution'] ?? 'random', ['random', 'even', 'weighted'], 'product_distribution');

        if ($params['time_distribution'] === 'single') {
            $params['single_point'] = $this->normalizeSinglePoint($params['single_point'] ?? null);
        }

        $params['status_mix'] = $params['status_mix'] ?? ['selesai' => 80, 'dibatalkan' => 10, 'kedaluwarsa' => 10];
        $this->validateStatusMix($params['status_mix']);

        $params['funnel_view_to_cart'] = $this->normalizeRatio($params['funnel_view_to_cart'] ?? 40, 'funnel_view_to_cart');
        $params['funnel_cart_to_booking'] = $this->normalizeRatio($params['funnel_cart_to_booking'] ?? 60, 'funnel_cart_to_booking');

        $params['lag_minutes'] = $this->normalizeLags($params['lag_minutes'] ?? self::DEFAULT_LAG_MINUTES);

        $methods = $params['payment_methods'] ?? [];

        if (! is_array($methods) || $methods === []) {
            throw new InvalidArgumentException('Daftar metode pembayaran tidak boleh kosong.');
        }

        $params['payment_methods'] = array_values(array_unique(array_map('strval', $methods)));

        $expiry = $params['payment_expiry_minutes'] ?? self::DEFAULT_PAYMENT_EXPIRY_MINUTES;

        if (! is_int($expiry) || $expiry < 1) {
            throw new InvalidArgumentException("'payment_expiry_minutes' harus bilangan bulat ≥ 1.");
        }

        $params['payment_expiry_minutes'] = $expiry;

        $maxDays = $params['max_period_days'] ?? 366;

        if (! is_int($maxDays) || $maxDays < 1) {
            throw new InvalidArgumentException("'max_period_days' harus bilangan bulat ≥ 1.");
        }

        $params['max_period_days'] = $maxDays;

        return $params;
    }

    private function normalizeSinglePoint(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->setTimezone('UTC');
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value, 'UTC');
        }

        throw new InvalidArgumentException("Distribusi 'satu titik' membutuhkan tanggal+jam ('single_point').");
    }

    private function normalizeEnum(mixed $value, array $allowed, string $key): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("'{$key}' harus salah satu dari: ".implode(', ', $allowed).'.');
        }

        return $value;
    }

    private function normalizeRatio(mixed $value, string $key): int
    {
        if (! is_int($value) || $value < 1 || $value > 100) {
            throw new InvalidArgumentException("'{$key}' harus bilangan bulat 1–100 (SIM-12).");
        }

        return $value;
    }

    /** @return array{0:int, 1:int} */
    private function normalizeRange(mixed $value, string $key, int $minFloor, ?int $maxCeil): array
    {
        if (! is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException("'{$key}' harus pasangan [min, max].");
        }

        [$min, $max] = array_values($value);

        if (! is_int($min) || ! is_int($max) || $min < $minFloor || $min > $max || ($maxCeil !== null && $max > $maxCeil)) {
            throw new InvalidArgumentException("'{$key}' tidak valid (min ≥ {$minFloor}, min ≤ max".($maxCeil !== null ? ", max ≤ {$maxCeil}" : '').').');
        }

        return [$min, $max];
    }

    /** @return array<string,array{0:int, 1:int}> */
    private function normalizeLags(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("'lag_minutes' harus array.");
        }

        $out = [];

        foreach (['view_to_cart', 'cart_to_booking', 'booking_to_paid'] as $key) {
            $out[$key] = $this->normalizeRange($value[$key] ?? self::DEFAULT_LAG_MINUTES[$key], "lag_minutes.{$key}", 0, null);
        }

        return $out;
    }

    /** @param array<string,int> $mix */
    private function validateStatusMix(array $mix): void
    {
        if ($mix === []) {
            throw new InvalidArgumentException('Campuran status akhir tidak boleh kosong.');
        }

        $sum = 0;

        foreach ($mix as $status => $pct) {
            if (! in_array($status, StatusAssigner::FINAL_STATUSES, true)) {
                throw new InvalidArgumentException("Status akhir tidak dikenal: '{$status}'.");
            }

            if (! is_int($pct) || $pct < 0 || $pct > 100) {
                throw new InvalidArgumentException("Persentase status '{$status}' harus bilangan bulat 0–100.");
            }

            $sum += $pct;
        }

        if ($sum !== 100) {
            throw new InvalidArgumentException("Campuran status akhir harus berjumlah 100% (saat ini {$sum}%).");
        }
    }
}
