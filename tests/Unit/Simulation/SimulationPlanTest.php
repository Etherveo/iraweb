<?php

use App\Simulation\FunnelPlanner;
use App\Simulation\SimulationPlan;
use Carbon\CarbonImmutable;

// Pure unit tests: no database, no HTTP. The period is fixed in October
// 2026 (safely in the past), so the SIM-28 "no future end" rule never
// depends on the day the suite runs.
function m4SimPlanParams(array $overrides = []): array
{
    return array_merge([
        'action' => 'tx_period_first',
        'user_ids' => [1, 2, 3],
        'products' => [
            ['id' => 1, 'price_idr' => 10000],
            ['id' => 2, 'price_idr' => 15000],
            ['id' => 3, 'price_idr' => 20000],
            ['id' => 4, 'price_idr' => 25000],
            ['id' => 5, 'price_idr' => 50000],
        ],
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-07',
        'transaction_count' => 20,
        // The planner is pure and never reads config, so the caller
        // must supply the active payment-method codes (D1).
        'payment_methods' => ['qris', 'dana', 'gopay', 'va_bri', 'va_bca', 'va_bni'],
    ], $overrides);
}

function m4FunnelCounts(SimulationPlan $plan): array
{
    $items = 0;
    $purchases = 0;

    foreach ($plan->orders as $order) {
        $items += count($order['items']);

        if ($order['final_status'] === 'selesai') {
            $purchases += count($order['items']);
        }
    }

    $orphanViews = count(array_filter($plan->orphanEvents, fn (array $e): bool => $e['type'] === 'view'));
    $orphanCarts = count(array_filter($plan->orphanEvents, fn (array $e): bool => $e['type'] === 'add_to_cart'));

    return [
        'views' => $items + $orphanViews,
        'carts' => $items + $orphanCarts,
        'bookings' => $items,
        'purchases' => $purchases,
        'items' => $items,
        'orphan_views' => $orphanViews,
        'orphan_carts' => $orphanCarts,
    ];
}

test('parameter dan seed yang sama menghasilkan plan yang identik', function () {
    $planner = new FunnelPlanner;

    $a = $planner->plan(m4SimPlanParams(), 20261101);
    $b = $planner->plan(m4SimPlanParams(), 20261101);

    expect($a->paramsHash)->toBe($b->paramsHash);
    expect($a->toArray())->toEqual($b->toArray());
});

test('urutan input yang berbeda tetap menghasilkan plan yang sama (masukan diurutkan)', function () {
    $planner = new FunnelPlanner;

    $base = $planner->plan(m4SimPlanParams(), 20261101);
    $shuffled = $planner->plan(m4SimPlanParams(['user_ids' => [3, 1, 2]]), 20261101);

    expect($shuffled->paramsHash)->toBe($base->paramsHash);
    expect($shuffled->toArray())->toEqual($base->toArray());
});

test('hash berubah bila parameter atau seed berubah', function () {
    $planner = new FunnelPlanner;

    $base = $planner->plan(m4SimPlanParams(), 20261101);

    $changedCount = $planner->plan(m4SimPlanParams(['transaction_count' => 21]), 20261101);
    expect($changedCount->paramsHash)->not->toBe($base->paramsHash);

    $changedMix = $planner->plan(m4SimPlanParams(['status_mix' => ['selesai' => 70, 'dibatalkan' => 20, 'kedaluwarsa' => 10]]), 20261101);
    expect($changedMix->paramsHash)->not->toBe($base->paramsHash);

    $changedSeed = $planner->plan(m4SimPlanParams(), 20261102);
    expect($changedSeed->paramsHash)->not->toBe($base->paramsHash);
    expect($changedSeed->toArray())->not->toEqual($base->toArray());
});

test('invarian funnel: view ≥ cart ≥ booking ≥ purchase', function () {
    $plan = (new FunnelPlanner)->plan(m4SimPlanParams(), 20261101);
    $counts = m4FunnelCounts($plan);

    expect($counts['views'])->toBeGreaterThanOrEqual($counts['carts']);
    expect($counts['carts'])->toBeGreaterThanOrEqual($counts['bookings']);
    expect($counts['bookings'])->toBeGreaterThanOrEqual($counts['purchases']);

    // Orphan counts follow the §9.5 formulas exactly.
    $expectedCartsTotal = (int) ceil($counts['items'] / 0.6);
    $expectedViewsTotal = (int) ceil($expectedCartsTotal / 0.4);

    expect($counts['orphan_carts'])->toBe($expectedCartsTotal - $counts['items']);
    expect($counts['orphan_views'])->toBe($expectedViewsTotal - $expectedCartsTotal);
});

test('campuran status akhir mengikuti rasio largest remainder secara tepat', function () {
    $plan = (new FunnelPlanner)->plan(m4SimPlanParams(['transaction_count' => 10]), 20261101);

    $counts = array_count_values(array_column($plan->orders, 'final_status'));

    expect($counts['selesai'] ?? 0)->toBe(8);
    expect($counts['dibatalkan'] ?? 0)->toBe(1);
    expect($counts['kedaluwarsa'] ?? 0)->toBe(1);
});

test('rantai funnel tiap pesanan berurutan dan paid_at tidak melewati batas bayar', function () {
    $plan = (new FunnelPlanner)->plan(m4SimPlanParams(), 20261101);

    foreach ($plan->orders as $order) {
        expect($order['view_at'] <= $order['add_to_cart_at'])->toBeTrue();
        expect($order['add_to_cart_at'] <= $order['booking_at'])->toBeTrue();

        if ($order['final_status'] === 'selesai') {
            expect($order['booking_at'] <= $order['paid_at'])->toBeTrue();
            expect($order['paid_at'] <= $order['payment_deadline_at'])->toBeTrue();
            expect($order['cancelled_at'])->toBeNull();
            expect($order['payment_status'])->toBe('paid');
        } else {
            expect($order['booking_at'] <= $order['cancelled_at'])->toBeTrue();
            expect($order['paid_at'])->toBeNull();
            expect($order['payment_status'])->toBeIn(['cancelled', 'expired']);
        }
    }
});

test('semua stempel waktu berada di dalam periode', function () {
    $plan = (new FunnelPlanner)->plan(m4SimPlanParams(), 20261101);

    $start = $plan->period->startUtc;
    $end = $plan->period->endUtc;
    $violations = [];

    foreach ($plan->orders as $index => $order) {
        foreach (['view_at', 'add_to_cart_at', 'booking_at', 'payment_deadline_at'] as $key) {
            if ($order[$key] < $start || $order[$key] > $end) {
                $violations[] = "order #{$index} {$key} di luar periode";
            }
        }

        foreach (['paid_at', 'cancelled_at'] as $key) {
            if ($order[$key] !== null && ($order[$key] < $start || $order[$key] > $end)) {
                $violations[] = "order #{$index} {$key} di luar periode";
            }
        }
    }

    foreach ($plan->orphanEvents as $index => $event) {
        if ($event['occurred_at'] < $start || $event['occurred_at'] > $end) {
            $violations[] = "orphan #{$index} di luar periode";
        }
    }

    expect($violations)->toBe([]);
});

test('distribusi merata antar-user berselisih paling banyak 1', function () {
    $planner = new FunnelPlanner;

    $plan = $planner->plan(m4SimPlanParams([
        'transaction_count' => 10,
        'user_distribution' => 'even',
    ]), 7);

    $perUser = array_count_values(array_column($plan->orders, 'user_id'));

    expect(array_sum($perUser))->toBe(10);
    expect(max($perUser) - min($perUser))->toBeLessThanOrEqual(1);

    // Deterministic: built twice, identical.
    $again = $planner->plan(m4SimPlanParams([
        'transaction_count' => 10,
        'user_distribution' => 'even',
    ]), 7);

    expect($again->toArray())->toEqual($plan->toArray());
});

test('distribusi satu titik: semua booking pada titik yang diminta, rantai tetap berurutan', function () {
    $point = CarbonImmutable::createFromFormat('Y-m-d H:i:s', '2026-10-04 12:00:00', 'UTC');

    $plan = (new FunnelPlanner)->plan(m4SimPlanParams([
        'time_distribution' => 'single',
        'single_point' => $point,
    ]), 99);

    foreach ($plan->orders as $order) {
        expect($order['booking_at'])->toEqual($point);
        expect($order['view_at'] <= $order['add_to_cart_at'])->toBeTrue();
        expect($order['add_to_cart_at'] <= $order['booking_at'])->toBeTrue();
    }
});

test('distribusi satu titik di tepi periode: rantai terkompresi tapi tetap di dalam periode', function () {
    $planner = new FunnelPlanner;

    // Booking exactly at the period start (2026-10-01 00:00 WITA).
    $atStart = CarbonImmutable::createFromFormat('Y-m-d H:i:s', '2026-09-30 16:00:00', 'UTC');
    $plan = $planner->plan(m4SimPlanParams([
        'time_distribution' => 'single',
        'single_point' => $atStart,
    ]), 5);

    foreach ($plan->orders as $order) {
        expect($order['booking_at'])->toEqual($atStart);
        expect($order['view_at'])->toEqual($atStart);
        expect($order['add_to_cart_at'])->toEqual($atStart);
    }

    // Booking exactly at the period end (2026-10-07 23:59:59.999 WITA).
    $atEnd = CarbonImmutable::createFromFormat('Y-m-d H:i:s.u', '2026-10-07 15:59:59.999000', 'UTC');
    $plan = $planner->plan(m4SimPlanParams([
        'time_distribution' => 'single',
        'single_point' => $atEnd,
    ]), 5);

    foreach ($plan->orders as $order) {
        expect($order['booking_at'])->toEqual($atEnd);
        expect($order['payment_deadline_at'])->toEqual($atEnd);

        if ($order['final_status'] === 'selesai') {
            expect($order['paid_at'])->toEqual($atEnd);
        } else {
            expect($order['cancelled_at'])->toEqual($atEnd);
        }
    }
});

test('rencana funnel (Aksi 4) menghasilkan jumlah event yang diminta secara tepat', function () {
    $planner = new FunnelPlanner;

    $params = [
        'action' => 'funnel_events',
        'user_ids' => [1, 2],
        'products' => [
            ['id' => 1, 'price_idr' => 10000],
            ['id' => 2, 'price_idr' => 15000],
            ['id' => 3, 'price_idr' => 20000],
        ],
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-03',
        'view_count' => 100,
        'add_to_cart_count' => 60,
        'booking_count' => 36,
        'purchase_count' => 20,
        'payment_methods' => ['qris', 'dana', 'gopay', 'va_bri', 'va_bca', 'va_bni'],
    ];

    $plan = $planner->planFunnel($params, 20261101);

    expect(count($plan->orders))->toBe(36);

    $paid = count(array_filter($plan->orders, fn (array $o): bool => $o['final_status'] === 'selesai'));
    expect($paid)->toBe(20);

    $counts = m4FunnelCounts($plan);

    expect($counts['views'])->toBe(100);
    expect($counts['carts'])->toBe(60);
    expect($counts['bookings'])->toBe(36);
    expect($counts['purchases'])->toBe(20);

    // Each booking is a single-item order (qty 1).
    foreach ($plan->orders as $order) {
        expect(count($order['items']))->toBe(1);
        expect($order['items'][0]['quantity'])->toBe(1);
    }

    // Deterministic.
    $again = $planner->planFunnel($params, 20261101);
    expect($again->toArray())->toEqual($plan->toArray());
});

test('rencana funnel menolak jumlah yang tidak menyusut', function () {
    $planner = new FunnelPlanner;

    $params = [
        'action' => 'funnel_events',
        'user_ids' => [1],
        'products' => [['id' => 1, 'price_idr' => 10000]],
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-03',
        'view_count' => 10,
        'add_to_cart_count' => 20,
        'booking_count' => 5,
        'purchase_count' => 5,
        'payment_methods' => ['qris', 'dana', 'gopay', 'va_bri', 'va_bca', 'va_bni'],
    ];

    expect(fn () => $planner->planFunnel($params, 1))->toThrow(InvalidArgumentException::class);
});
