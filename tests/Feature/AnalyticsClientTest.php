<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AnalyticsClient;
use App\Services\AnalyticsFactsBuilder;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// M5 langkah 3: AnalyticsFactsBuilder + AnalyticsClient. No live Python
// service is needed for any test here (client tests use Http::fake).

function anaBuyer(): User
{
    return User::factory()->create();
}

function anaProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['stock_on_hand' => 10, 'stock_reserved' => 0, 'price_idr' => 5000],
        $attributes,
    ));
}

/** WITA datetime string -> UTC Carbon for storage (storage is UTC). */
function anaWita(string $wita): Carbon
{
    return Carbon::parse($wita, 'Asia/Makassar')->setTimezone('UTC');
}

/** Paid order with explicit paid_at (WITA string); lines are [product, qty, unit_price]. */
function anaPaidOrder(User $buyer, array $lines, string $paidAtWita, string $origin = 'normal', string $status = 'dibayar'): Order
{
    $total = 0;

    foreach ($lines as [$product, $qty, $price]) {
        $total += $qty * $price;
    }

    $order = Order::factory()->create([
        'user_id' => $buyer->id,
        'status' => $status,
        'total_idr' => $total,
        'paid_at' => anaWita($paidAtWita),
        'origin' => $origin,
        'simulation_run_id' => $origin === 'normal' ? null : 'ana-test-run',
    ]);

    foreach ($lines as [$product, $qty, $price]) {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_idr' => $price,
            'quantity' => $qty,
            'subtotal_idr' => $price * $qty,
            'origin' => $origin,
            'simulation_run_id' => $origin === 'normal' ? null : 'ana-test-run',
        ]);
    }

    return $order->fresh();
}

function anaEvent(string $type, Product $product, string $occurredAtWita, string $origin = 'normal', ?User $user = null): Event
{
    return Event::factory()->create([
        'event_type' => $type,
        'user_id' => ($user ?? User::factory()->create())->id,
        'product_id' => $product->id,
        'occurred_at' => anaWita($occurredAtWita),
        'origin' => $origin,
        'simulation_run_id' => $origin === 'normal' ? null : 'ana-test-run',
    ]);
}

// ---- AnalyticsFactsBuilder ----

test('M5 facts builder counts only paid order items for sales, events for funnel stages', function () {
    $buyer = anaBuyer();
    $cat1 = Category::factory()->create(['name' => 'Alat Tulis']);
    $cat2 = Category::factory()->create(['name' => 'Tas']);
    $a = anaProduct(['name' => 'Pulpen A', 'category_id' => $cat1->id, 'price_idr' => 5000]);
    $b = anaProduct(['name' => 'Tas B', 'category_id' => $cat2->id, 'price_idr' => 10000]);
    $c = anaProduct(['name' => 'Pensil C', 'category_id' => $cat1->id, 'price_idr' => 7000]);

    // Paid order, 2026-11-02 10:00 WITA: A x2 @5000, B x1 @10000.
    anaPaidOrder($buyer, [[$a, 2, 5000], [$b, 1, 10000]], '2026-11-02 10:00:00');

    // Unpaid order: C x3 — must not count as sales (booking event still counts).
    $unpaid = Order::factory()->create(['user_id' => $buyer->id, 'status' => 'menunggu_pembayaran', 'total_idr' => 21000]);
    OrderItem::factory()->create(['order_id' => $unpaid->id, 'product_id' => $c->id, 'product_name_snapshot' => 'Pensil C', 'unit_price_idr' => 7000, 'quantity' => 3, 'subtotal_idr' => 21000]);

    // Cancelled order: A x1 — must not count as sales.
    $cancelled = Order::factory()->create(['user_id' => $buyer->id, 'status' => 'dibatalkan', 'total_idr' => 5000]);
    OrderItem::factory()->create(['order_id' => $cancelled->id, 'product_id' => $a->id, 'product_name_snapshot' => 'Pulpen A', 'unit_price_idr' => 5000, 'quantity' => 1, 'subtotal_idr' => 5000]);

    // Events on 2026-11-02 (WITA).
    anaEvent('view', $a, '2026-11-02 09:00:00');
    anaEvent('view', $a, '2026-11-02 09:05:00');
    anaEvent('add_to_cart', $a, '2026-11-02 09:10:00');
    anaEvent('booking', $a, '2026-11-02 09:15:00');
    anaEvent('purchase', $a, '2026-11-02 10:00:00');
    anaEvent('view', $b, '2026-11-02 09:30:00');
    anaEvent('booking', $b, '2026-11-02 09:35:00');
    anaEvent('purchase', $b, '2026-11-02 10:00:00');
    anaEvent('booking', $c, '2026-11-02 11:00:00');
    anaEvent('cancel', $c, '2026-11-02 12:00:00');

    $facts = app(AnalyticsFactsBuilder::class)->buildFacts('2026-11-02', '2026-11-02');

    expect($facts)->toHaveCount(3);
    $byProduct = collect($facts)->keyBy('product_id');

    expect($byProduct[$a->id])->toMatchArray([
        'date' => '2026-11-02', 'product_id' => $a->id,
        'units_sold' => 2, 'revenue_idr' => 10000, 'orders_paid' => 1,
        'views' => 2, 'add_to_carts' => 1, 'bookings' => 1, 'purchases' => 1, 'cancels' => 0,
    ]);
    expect($byProduct[$b->id])->toMatchArray([
        'date' => '2026-11-02', 'product_id' => $b->id,
        'units_sold' => 1, 'revenue_idr' => 10000, 'orders_paid' => 1,
        'views' => 1, 'add_to_carts' => 0, 'bookings' => 1, 'purchases' => 1, 'cancels' => 0,
    ]);
    expect($byProduct[$c->id])->toMatchArray([
        'date' => '2026-11-02', 'product_id' => $c->id,
        'units_sold' => 0, 'revenue_idr' => 0, 'orders_paid' => 0,
        'views' => 0, 'add_to_carts' => 0, 'bookings' => 1, 'purchases' => 0, 'cancels' => 1,
    ]);
});

test('M5 facts builder counts paid orders that advanced to diproses or selesai', function () {
    // Regresi 2026-11-11: penjualan hanya dihitung dari order yang
    // statusnya masih 'dibayar'. Order yang sudah maju ke 'diproses' /
    // 'selesai' — ~80% order simulator berakhir 'selesai' — tidak
    // terhitung, padahal K9 menghitung pembelian saat dibayar (paid_at),
    // bukan dari status saat ini.
    $buyer = anaBuyer();
    $a = anaProduct(['name' => 'Pulpen A']);
    $b = anaProduct(['name' => 'Pulpen B']);

    anaPaidOrder($buyer, [[$a, 2, 5000]], '2026-11-02 10:00:00', 'normal', 'diproses');
    anaPaidOrder($buyer, [[$b, 1, 5000]], '2026-11-02 11:00:00', 'normal', 'selesai');

    $facts = app(AnalyticsFactsBuilder::class)->buildFacts('2026-11-02', '2026-11-02');

    expect($facts)->toHaveCount(2);
    $byProduct = collect($facts)->keyBy('product_id');
    expect($byProduct[$a->id])->toMatchArray([
        'date' => '2026-11-02', 'product_id' => $a->id,
        'units_sold' => 2, 'revenue_idr' => 10000, 'orders_paid' => 1,
    ]);
    expect($byProduct[$b->id])->toMatchArray([
        'date' => '2026-11-02', 'product_id' => $b->id,
        'units_sold' => 1, 'revenue_idr' => 5000, 'orders_paid' => 1,
    ]);

    $totals = app(AnalyticsFactsBuilder::class)->buildOrderTotals('2026-11-02', '2026-11-02');

    expect($totals)->toHaveCount(1);
    expect($totals[0]['orders_paid'])->toBe(2);
});

test('M5 facts builder buckets by WITA date, not UTC', function () {
    $buyer = anaBuyer();
    $a = anaProduct(['name' => 'Pulpen WITA']);
    // Paid 2026-11-02 00:30 WITA = 2026-11-01 16:30 UTC.
    anaPaidOrder($buyer, [[$a, 1, 5000]], '2026-11-02 00:30:00');
    anaEvent('view', $a, '2026-11-02 00:15:00');

    $builder = app(AnalyticsFactsBuilder::class);

    $facts = $builder->buildFacts('2026-11-02', '2026-11-02');
    expect($facts)->toHaveCount(1);
    expect($facts[0]['date'])->toBe('2026-11-02');
    expect($facts[0]['units_sold'])->toBe(1);
    expect($facts[0]['views'])->toBe(1);

    // The same rows must NOT appear on the UTC date.
    expect($builder->buildFacts('2026-11-01', '2026-11-01'))->toBe([]);
});

test('M5 order totals count unique paid orders per day, by_category may exceed the total', function () {
    $buyer = anaBuyer();
    $cat1 = Category::factory()->create(['name' => 'Alat Tulis']);
    $cat2 = Category::factory()->create(['name' => 'Tas']);
    $a = anaProduct(['name' => 'Pulpen A', 'category_id' => $cat1->id, 'price_idr' => 5000]);
    $b = anaProduct(['name' => 'Tas B', 'category_id' => $cat2->id, 'price_idr' => 10000]);

    // One paid order with items in BOTH categories + one paid order with only A.
    anaPaidOrder($buyer, [[$a, 1, 5000], [$b, 1, 10000]], '2026-11-02 10:00:00');
    anaPaidOrder($buyer, [[$a, 2, 5000]], '2026-11-02 15:00:00');

    $totals = app(AnalyticsFactsBuilder::class)->buildOrderTotals('2026-11-02', '2026-11-02');

    expect($totals)->toHaveCount(1);
    expect($totals[0]['date'])->toBe('2026-11-02');
    expect($totals[0]['orders_paid'])->toBe(2);
    expect($totals[0]['by_category'])->toBe([
        ['category_id' => $cat1->id, 'orders_paid' => 2],
        ['category_id' => $cat2->id, 'orders_paid' => 1],
    ]);
});

test('M5 facts builder rejects invalid periods', function () {
    $builder = app(AnalyticsFactsBuilder::class);

    expect(fn () => $builder->buildFacts('2026-11-03', '2026-11-02'))
        ->toThrow(InvalidArgumentException::class);
    // 2025-11-01..2026-11-02 is 367 days, over the 366-day cap.
    expect(fn () => $builder->buildFacts('2025-11-01', '2026-11-02'))
        ->toThrow(InvalidArgumentException::class);
});

test('M5 facts builder filters by origin', function () {
    $buyer = anaBuyer();
    $a = anaProduct(['name' => 'Pulpen A']);
    anaPaidOrder($buyer, [[$a, 1, 5000]], '2026-11-02 10:00:00', 'normal');
    anaPaidOrder($buyer, [[$a, 1, 5000]], '2026-11-02 11:00:00', 'developer_simulation');
    anaEvent('view', $a, '2026-11-02 09:00:00', 'normal');
    anaEvent('view', $a, '2026-11-02 09:30:00', 'developer_simulation');

    $builder = app(AnalyticsFactsBuilder::class);

    $all = $builder->buildFacts('2026-11-02', '2026-11-02');
    expect($all)->toHaveCount(1);
    expect($all[0]['units_sold'])->toBe(2);
    expect($all[0]['views'])->toBe(2);

    $normal = $builder->buildFacts('2026-11-02', '2026-11-02', 'normal');
    expect($normal[0]['units_sold'])->toBe(1);
    expect($normal[0]['views'])->toBe(1);

    $sim = $builder->buildFacts('2026-11-02', '2026-11-02', 'developer_simulation');
    expect($sim[0]['units_sold'])->toBe(1);
    expect($sim[0]['views'])->toBe(1);

    expect(fn () => $builder->buildFacts('2026-11-02', '2026-11-02', 'ngawur'))
        ->toThrow(InvalidArgumentException::class);
});

test('M5 facts builder build() assembles the full request payload', function () {
    $buyer = anaBuyer();
    $a = anaProduct(['name' => 'Pulpen A']);
    anaPaidOrder($buyer, [[$a, 1, 5000]], '2026-11-02 10:00:00');

    $payload = app(AnalyticsFactsBuilder::class)->build('2026-11-02', '2026-11-02');

    expect($payload['period'])->toBe(['start' => '2026-11-02', 'end' => '2026-11-02']);
    expect($payload['origin_filter'])->toBe('all');
    expect($payload['facts'])->toHaveCount(1);
    expect($payload['products'])->not->toBeEmpty();
    expect(collect($payload['products'])->firstWhere('id', $a->id)['name'])->toBe('Pulpen A');
    expect($payload['order_totals'])->toHaveCount(1);
    expect($payload['order_totals'][0]['orders_paid'])->toBe(1);
});

test('M5 facts builder leaves active_since null so the trend series starts at first activity', function () {
    // Regresi 2026-11-10: mengirim created_at (tanggal seed Nov 2026)
    // sebagai active_since memotong deret tren tiap produk menjadi 0
    // minggu penuh, karena riwayat penjualan simulasi di-backdate ke
    // Sep–Okt 2026 — sebelum produk "ada". Modul harus memakai fallback
    // minggu pertama yang punya aktivitas.
    $a = anaProduct(['name' => 'Pulpen A']);

    $payload = app(AnalyticsFactsBuilder::class)->build('2026-11-02', '2026-11-02');

    expect(collect($payload['products'])->firstWhere('id', $a->id)['active_since'])->toBeNull();
});

test('M5 facts builder sends release_date as active_since when set', function () {
    $a = anaProduct(['name' => 'Pulpen A', 'release_date' => '2026-09-01']);

    $payload = app(AnalyticsFactsBuilder::class)->build('2026-11-02', '2026-11-02');

    expect(collect($payload['products'])->firstWhere('id', $a->id)['active_since'])->toBe('2026-09-01');
});

// ---- AnalyticsClient ----

test('M5 analytics client returns the parsed result on success', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/summary' => Http::response(['algorithm_version' => 'x', 'kpis' => []], 200),
    ]);

    $client = new AnalyticsClient('http://127.0.0.1:8001', 'test-key', 5);
    $result = $client->summary([
        'facts' => [],
        'products' => [],
        'period' => ['start' => '2026-11-02', 'end' => '2026-11-02'],
    ]);

    expect($result)->toBe(['algorithm_version' => 'x', 'kpis' => []]);
    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Internal-Key', 'test-key')
            && $request->url() === 'http://127.0.0.1:8001/v1/summary';
    });
});

test('M5 analytics client returns null when the service is unreachable', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $client = new AnalyticsClient('http://127.0.0.1:8001', 'test-key', 5);

    expect($client->summary(['facts' => []]))->toBeNull();
});

test('M5 analytics client throws a clear exception on 422', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/summary' => Http::response(
            ['error' => 'validation', 'message' => 'period: start tidak boleh setelah end.'],
            422,
        ),
    ]);

    $client = new AnalyticsClient('http://127.0.0.1:8001', 'test-key', 5);

    expect(fn () => $client->summary(['facts' => []]))
        ->toThrow(InvalidArgumentException::class, 'period: start tidak boleh setelah end.');
});

test('M5 analytics client throws a clear exception on 401', function () {
    Http::fake([
        'http://127.0.0.1:8001/v1/summary' => Http::response(
            ['error' => 'unauthorized', 'message' => 'X-Internal-Key salah.'],
            401,
        ),
    ]);

    $client = new AnalyticsClient('http://127.0.0.1:8001', 'test-key', 5);

    expect(fn () => $client->summary(['facts' => []]))
        ->toThrow(RuntimeException::class, 'Kunci internal analitik ditolak');
});

test('M5 analytics client throws when the internal key is missing', function () {
    config(['analytics.key' => null]);

    $client = new AnalyticsClient();

    expect(fn () => $client->summary(['facts' => []]))
        ->toThrow(InvalidArgumentException::class, 'ANALYTICS_INTERNAL_KEY belum diisi');
});

test('M5 analytics client only sends the keys each endpoint accepts', function () {
    Http::fake(fn () => Http::response(['ok' => true], 200));

    $client = new AnalyticsClient('http://127.0.0.1:8001', 'test-key', 5);

    // Payload lengkap seperti hasil AnalyticsFactsBuilder::build() plus
    // tambahan controller (metric, n_min_weeks, top_n, previous_*).
    $payload = [
        'facts' => [],
        'products' => [],
        'period' => ['start' => '2026-11-02', 'end' => '2026-11-02'],
        'origin_filter' => 'all',
        'categories' => [['category_id' => 1]],
        'order_totals' => [],
        'metric' => 'units',
        'n_min_weeks' => 8,
        'top_n' => 5,
        'previous_facts' => [],
        'previous_order_totals' => [],
    ];

    $client->summary($payload);
    $client->trend($payload);
    $client->funnel($payload);

    // /v1/summary menerima order_totals, tetapi bukan categories.
    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'http://127.0.0.1:8001/v1/summary'
            && isset($data['order_totals'])
            && ! isset($data['categories'])
            && ! isset($data['metric']);
    });

    // /v1/trend dan /v1/funnel tidak menerima categories maupun order_totals.
    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'http://127.0.0.1:8001/v1/trend'
            && isset($data['metric'])
            && isset($data['n_min_weeks'])
            && ! isset($data['categories'])
            && ! isset($data['order_totals']);
    });

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'http://127.0.0.1:8001/v1/funnel'
            && isset($data['facts'])
            && ! isset($data['categories'])
            && ! isset($data['order_totals'])
            && ! isset($data['metric']);
    });
});
