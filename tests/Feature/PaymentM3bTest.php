<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentHttpTrace;
use App\Models\Product;
use App\Models\User;
use App\Payments\PaymentAdapter;
use App\Payments\ProviderRegistry;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// M3b PAY-01..36, PAY-55..63. Payment simulator core: instruction
// page, simulator page, webhook + signed internal transport (PAY-36),
// repay, scheduled expiry, traces. No Python, no Figma images, no
// external HTTP: every provider event goes through the app's own
// webhook endpoint via InternalDispatcher.
//
// QR: no QR library is installed (Ren will send his own JS/Python
// script). The instruction page therefore renders the TextQrRenderer
// fallback (labelled text, not a QR) — see the QR fallback test.
// The webhook secret for tests is set via config()->set here, never
// in phpunit.xml or .env.

beforeEach(function () {
    config()->set('payment.webhook_secret', 'test-secret');
});

function m3bBuyer(): User
{
    return User::factory()->create();
}

function m3bAdmin(): User
{
    return User::factory()->admin()->create();
}

function m3bProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['stock_on_hand' => 10, 'stock_reserved' => 0, 'price_idr' => 5000],
        $attributes,
    ));
}

// Build a cart through the real HTTP routes, then check out. Returns
// the created order (fresh). The checkout POST now redirects to the
// payment instruction page (M3b).
function m3bCheckout($t, User $buyer, array $lines, string $method = 'qris', ?string $key = null): Order
{
    $t->actingAs($buyer, 'web');

    foreach ($lines as $line) {
        $t->post('/keranjang/tambah', [
            'product_id' => $line['product']->id,
            'quantity' => $line['qty'],
        ])->assertRedirect('/keranjang');
    }

    $t->post('/checkout', [
        'payment_method' => $method,
        'checkout_key' => $key ?? (string) Str::uuid(),
    ])->assertRedirect();

    return Order::where('user_id', $buyer->id)->latest('id')->first();
}

function m3bSign(string $body): string
{
    return 'sim-hmac-sha256='.hash_hmac('sha256', $body, 'test-secret');
}

// Call the webhook with the EXACT raw body bytes that were signed
// (postJson would re-encode and could change the bytes).
function m3bWebhook($t, array $payload, string $mode = 'valid'): TestResponse
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    if ($mode === 'valid') {
        $server['HTTP_X_SIMULATOR_SIGNATURE'] = m3bSign($body);
    } elseif ($mode === 'wrong') {
        $server['HTTP_X_SIMULATOR_SIGNATURE'] = 'sim-hmac-sha256='.str_repeat('0', 64);
    }

    return $t->call('POST', '/api/v1/webhooks/payments/simulator', [], [], [], $server, $body);
}

function m3bPaidPayload(Payment $payment): array
{
    return [
        'payment_id' => $payment->id,
        'order_id' => $payment->order->order_number,
        'status' => 'paid',
        'paid_at' => now()->toIso8601String(),
        'reference' => $payment->reference_code,
    ];
}

// ---- Instruction page ----

test('PAY-11 instruction page shows the sim labels, reference, total, countdown data and the QR fallback for its owner', function () {
    // Pin the app URL so the loopback warning assertion does not
    // depend on the developer's local .env.
    config()->set('app.url', 'http://localhost:8000');

    $buyer = m3bBuyer();
    $product = m3bProduct(['name' => 'Pulpen Instruksi', 'price_idr' => 7000]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 2]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertOk()
        ->assertSee('Pembayaran')
        ->assertDontSee('SIMULASI')
        ->assertSee($order->order_number)
        ->assertSee('Rp 14.000')
        ->assertSee('QRIS')
        ->assertSee($payment->reference_code)
        ->assertSee('QR belum tersedia')
        ->assertSee('data-qr-url', false)
        ->assertSee('/pay/sim/'.$payment->payment_token, false)
        ->assertSee('Buka Halaman Pembayaran')
        ->assertSee('loopback')
        ->assertSee($payment->expires_at->toIso8601String(), false)
        ->assertDontSee($buyer->name);
});

test('PAY-11 instruction page is owner-only: another buyer gets 404, a guest is redirected', function () {
    $buyer = m3bBuyer();
    $other = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);

    $this->actingAs($other, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertNotFound();

    $this->post('/keluar');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertRedirect('/masuk');
});

test('PAY-09 VA instruction shows the 16-digit VA number grouped, a copy button, and the small QR fallback', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]], 'va_bri');
    $payment = $order->payments()->first();

    expect($payment->reference_code)->toMatch('/^\d{16}$/');
    expect($payment->reference_code)->toStartWith('99901');

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertOk()
        ->assertSee('VA BRI')
        ->assertSee('Nomor VA — bukan nomor rekening.')
        ->assertDontSee('SIMULASI')
        ->assertSee($payment->formattedReference())
        ->assertSee('Salin')
        ->assertSee('QR belum tersedia')
        ->assertSee('Kode QR pendamping');
});

test('PAY-13 status endpoint returns the payment and order status, and redirects only when final', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertOk()
        ->assertJson(['status' => 'pending', 'order_status' => 'menunggu_pembayaran', 'redirect_url' => null]);

    // Pay from the simulator page (another device, token only).
    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();

    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertOk()
        ->assertJson(['status' => 'paid', 'order_status' => 'dibayar'])
        // M3c PAY-13: a final payment now redirects to its result
        // page (PAY-45), not to the order detail.
        ->assertJsonPath('redirect_url', route('pembayaran.hasil', $payment));

    // Another buyer cannot poll this payment.
    $this->actingAs(m3bBuyer(), 'web');
    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertNotFound();
});

test('PAY-11 instruction page redirects when the order is no longer waiting or has no pending payment', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();

    $this->get('/pesanan/'.$order->id.'/bayar')->assertRedirect('/pesanan/'.$order->id);
});

test('ORD-02 checkout success redirects to the payment instruction page', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');

    $response = $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
    ]);

    $order = Order::where('user_id', $buyer->id)->latest('id')->first();
    $response->assertRedirect(route('pesanan.bayar', $order));
});

// ---- Simulator page ----

test('PAY-15 sim page shows the order without any buyer personal data', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(['name' => 'Buku Sim']), 'qty' => 2]]);
    $payment = $order->payments()->first();

    $this->get('/pay/sim/'.$payment->payment_token)->assertOk()
        ->assertSee('Halaman Pembayaran')
        ->assertSee($order->order_number)
        ->assertSee('Buku Sim')->assertSee('Rp 10.000')
        ->assertSee('QRIS')
        ->assertSee($payment->reference_code)
        ->assertSee('Bayar Sekarang')
        ->assertSee('Gagalkan Pembayaran')
        ->assertSee('Kembali ke Pesanan')
        ->assertDontSee($buyer->name)
        ->assertDontSee($buyer->email);
});

test('PAY-19 unknown or malformed token returns a generic 404', function () {
    $this->get('/pay/sim/'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='))->assertNotFound();
    $this->get('/pay/sim/xyz')->assertNotFound();
    $this->post('/pay/sim/xyz/bayar')->assertNotFound();
});

test('PAY-16 sim Pay marks the payment paid, the order dibayar, records purchase events and confirms stock — once only', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['name' => 'Pulpen Bayar', 'stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 2]]);
    $payment = $order->payments()->first();

    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk()
        ->assertSee('berhasil');

    expect($payment->fresh()->status)->toBe('paid');
    expect($payment->fresh()->paid_at)->not->toBeNull();
    expect($order->fresh()->status)->toBe('dibayar');
    expect($product->fresh()->stock_reserved)->toBe(0);
    expect($product->fresh()->stock_on_hand)->toBe(8);
    // 1 add_to_cart + 1 booking + 1 purchase (one event per order
    // ITEM row; this order has one row with qty 2).
    $this->assertDatabaseCount('events', 3);
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(1);

    // PAY-36 proof: the action really went through the webhook.
    $this->assertDatabaseHas('payment_http_traces', [
        'payment_id' => $payment->id, 'kind' => 'callback', 'response_status' => 200,
    ]);

    // A second Pay is a no-op: same status, no double stock/events.
    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk()
        ->assertSee('sudah berstatus');
    expect($product->fresh()->stock_on_hand)->toBe(8);
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(1);
});

test('PAY-17 sim Fail marks the payment failed, keeps the order waiting and the stock reserved, with no purchase', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 2]]);
    $payment = $order->payments()->first();

    $this->post('/pay/sim/'.$payment->payment_token.'/gagal')->assertOk()
        ->assertSee('digagalkan');

    expect($payment->fresh()->status)->toBe('failed');
    expect($order->fresh()->status)->toBe('menunggu_pembayaran');
    expect($product->fresh()->stock_reserved)->toBe(2);
    expect($product->fresh()->stock_on_hand)->toBe(10);
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(0);

    $this->assertDatabaseHas('payment_http_traces', [
        'payment_id' => $payment->id, 'kind' => 'callback', 'response_status' => 200,
    ]);
});

test('PAY-20 expired token shows expired and rejects actions', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $payment = $order->payments()->first();

    Carbon::setTestNow(now()->addMinutes(31));

    $this->get('/pay/sim/'.$payment->payment_token)->assertOk()
        ->assertSee('Kedaluwarsa')
        ->assertDontSee('Bayar Sekarang');

    expect($payment->fresh()->status)->toBe('expired');
    expect($order->fresh()->status)->toBe('kedaluwarsa');
    expect($product->fresh()->stock_reserved)->toBe(0);

    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk()
        ->assertSee('sudah berstatus');
    expect($payment->fresh()->status)->toBe('expired');
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(0);

    Carbon::setTestNow();
});

test('PAY-21 final token shows the final status and rejects actions without changes', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();

    $this->get('/pay/sim/'.$payment->payment_token)->assertOk()
        ->assertSee('Terbayar')
        ->assertDontSee('Bayar Sekarang');

    $this->post('/pay/sim/'.$payment->payment_token.'/gagal')->assertOk()
        ->assertSee('sudah berstatus');
    expect($payment->fresh()->status)->toBe('paid');
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(1);
});

test('PAY-18 Back to Order does not change any status', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    // Logged in: the link goes to the order detail page.
    $this->actingAs($buyer, 'web');
    $this->get('/pay/sim/'.$payment->payment_token)->assertOk()
        ->assertSee(route('pesanan.show', $order), false);
    expect($payment->fresh()->status)->toBe('pending');

    // Guest: the link goes to the buyer login page.
    $this->post('/keluar');
    $this->get('/pay/sim/'.$payment->payment_token)->assertOk()
        ->assertSee(route('masuk'), false);
    expect($payment->fresh()->status)->toBe('pending');
});

test('PAY-36 the internal simulator gateway is unreachable from outside', function () {
    $this->postJson('/api/v1/simulator/payments', [
        'order_id' => 'IRA-000000-XXXXXX',
        'amount' => 1000,
        'currency' => 'IDR',
        'payment_method' => 'qris',
        'reference' => 'nope',
    ])->assertNotFound();
});

// ---- Webhook ----

test('PAY-24/25 webhook with a valid signature processes a paid callback', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 2]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk()
        ->assertJson(['result' => 'processed', 'payment_status' => 'paid', 'order_status' => 'dibayar']);

    expect($payment->fresh()->status)->toBe('paid');
    expect($order->fresh()->status)->toBe('dibayar');
    expect($product->fresh()->stock_reserved)->toBe(0);
    expect($product->fresh()->stock_on_hand)->toBe(8);
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(1);
});

test('PAY-25 missing or wrong signature returns 401 without any change', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment), 'missing')->assertStatus(401);
    m3bWebhook($this, m3bPaidPayload($payment), 'wrong')->assertStatus(401);

    expect($payment->fresh()->status)->toBe('pending');
    expect($order->fresh()->status)->toBe('menunggu_pembayaran');
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(0);
});

test('PAY-26 invalid payload returns 422 without changes', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $base = m3bPaidPayload($payment);

    m3bWebhook($this, array_merge($base, ['status' => 'bogus']))->assertStatus(422);
    m3bWebhook($this, array_diff_key($base, ['reference' => 1]))->assertStatus(422);
    m3bWebhook($this, array_diff_key($base, ['paid_at' => 1]))->assertStatus(422);
    m3bWebhook($this, array_merge($base, ['paid_at' => 'bukan-tanggal']))->assertStatus(422);
    m3bWebhook($this, array_merge($base, ['payment_id' => 'tidak-ada']))->assertStatus(404);

    expect($payment->fresh()->status)->toBe('pending');
    expect($order->fresh()->status)->toBe('menunggu_pembayaran');
});

test('PAY-26 order_id or reference mismatch returns 422', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, array_merge(m3bPaidPayload($payment), ['order_id' => 'IRA-000000-XXXXXX']))->assertStatus(422);
    m3bWebhook($this, array_merge(m3bPaidPayload($payment), ['reference' => 'SIMQR-XXXXXXXXXX']))->assertStatus(422);

    expect($payment->fresh()->status)->toBe('pending');
});

test('PAY-23 an amount in the payload that differs from the order is rejected', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, array_merge(m3bPaidPayload($payment), ['amount' => 999999]))->assertStatus(422);

    expect($payment->fresh()->status)->toBe('pending');
});

test('PAY-24 unknown provider returns 404', function () {
    $body = json_encode(['status' => 'paid']);
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIMULATOR_SIGNATURE' => m3bSign($body),
    ];

    $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], $server, $body)->assertNotFound();
});

test('PAY-27 the same callback twice returns 200 already_processed with no double effects', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk()
        ->assertJson(['result' => 'processed']);
    m3bWebhook($this, m3bPaidPayload($payment))->assertOk()
        ->assertJson(['result' => 'already_processed']);

    expect($payment->fresh()->status)->toBe('paid');
    expect($product->fresh()->stock_on_hand)->toBe(9);
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(1);
    $this->assertDatabaseCount('payment_status_transitions', 2); // null->pending, pending->paid
});

test('PAY-27 a different result for a final payment returns 409', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk();

    $failed = [
        'payment_id' => $payment->id,
        'order_id' => $payment->order->order_number,
        'status' => 'failed',
        'reference' => $payment->reference_code,
    ];
    m3bWebhook($this, $failed)->assertStatus(409);

    expect($payment->fresh()->status)->toBe('paid');
    expect($order->fresh()->status)->toBe('dibayar');
});

test('PAY-27 a paid callback after the deadline is processed as expired', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $payment = $order->payments()->first();

    Carbon::setTestNow(now()->addMinutes(31));

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk()
        ->assertJson(['result' => 'processed', 'payment_status' => 'expired']);

    expect($payment->fresh()->status)->toBe('expired');
    expect($order->fresh()->status)->toBe('kedaluwarsa');
    expect($product->fresh()->stock_reserved)->toBe(0);
    expect(\App\Models\Event::where('event_type', 'purchase')->count())->toBe(0);
    expect(\App\Models\Event::where('event_type', 'cancel')->where('reason', 'expired')->count())->toBe(1);

    Carbon::setTestNow();
});

// ---- Repay ----

test('PAY-31 after a failed payment the buyer can repay with another method', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]], 'qris');
    $oldPayment = $order->payments()->first();

    $this->post('/pay/sim/'.$oldPayment->payment_token.'/gagal')->assertOk();
    expect($oldPayment->fresh()->status)->toBe('failed');

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id)->assertOk()->assertSee('Bayar ulang');

    $this->post('/pesanan/'.$order->id.'/bayar-ulang', ['payment_method' => 'va_bca'])
        ->assertRedirect(route('pesanan.bayar', $order));

    $newPayment = $order->fresh()->payments()->where('status', 'pending')->first();
    expect($newPayment)->not->toBeNull();
    expect($newPayment->payment_method)->toBe('va_bca');
    expect($newPayment->id)->not->toBe($oldPayment->id);
    expect($newPayment->payment_token)->not->toBe($oldPayment->payment_token);
    expect($newPayment->reference_code)->not->toBe($oldPayment->reference_code);
    expect($newPayment->expires_at->equalTo($order->payment_deadline_at))->toBeTrue();
    expect($oldPayment->fresh()->status)->toBe('failed');
    expect($order->fresh()->payments()->where('status', 'pending')->count())->toBe(1);
});

test('PAY-31 repay is rejected while a payment is still pending', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);

    $this->actingAs($buyer, 'web');
    $this->post('/pesanan/'.$order->id.'/bayar-ulang', ['payment_method' => 'gopay'])
        ->assertRedirect(route('pesanan.bayar', $order));

    expect($order->fresh()->payments()->count())->toBe(1);
});

test('PAY-31 repay is rejected after the deadline', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->post('/pay/sim/'.$payment->payment_token.'/gagal')->assertOk();

    Carbon::setTestNow(now()->addMinutes(31));

    $this->actingAs($buyer, 'web');
    $this->post('/pesanan/'.$order->id.'/bayar-ulang', ['payment_method' => 'gopay'])
        ->assertRedirect(route('pesanan.show', $order));

    expect($order->fresh()->payments()->count())->toBe(1);
    expect($order->fresh()->status)->toBe('kedaluwarsa');

    Carbon::setTestNow();
});

// ---- Scheduled expiry ----

test('PAY-32/34 the scheduled command expires overdue orders through the webhook', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order1 = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $order2 = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 2]], 'gopay', (string) Str::uuid());

    Carbon::setTestNow(now()->addMinutes(31));

    $this->artisan('ira:expire-overdue-orders')->assertExitCode(0);

    foreach ([$order1, $order2] as $order) {
        expect($order->fresh()->status)->toBe('kedaluwarsa');
        expect($order->fresh()->payments()->first()->status)->toBe('expired');
    }
    expect($product->fresh()->stock_reserved)->toBe(0);
    expect(\App\Models\Event::where('event_type', 'cancel')->where('reason', 'expired')->count())->toBe(2);

    // The expired callbacks are real recorded HTTP events.
    $this->assertDatabaseCount('payment_http_traces', 4); // 2 create_payment + 2 expired_callback
    expect(PaymentHttpTrace::where('kind', 'expired_callback')->count())->toBe(2);

    // Idempotent: a second run changes nothing.
    $this->artisan('ira:expire-overdue-orders')->assertExitCode(0);
    $this->assertDatabaseCount('payment_http_traces', 4);

    Carbon::setTestNow();
});

test('PAY-34 an order without a pending payment after failure still expires via the command', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->post('/pay/sim/'.$payment->payment_token.'/gagal')->assertOk();

    Carbon::setTestNow(now()->addMinutes(31));

    $this->artisan('ira:expire-overdue-orders')->assertExitCode(0);

    expect($order->fresh()->status)->toBe('kedaluwarsa');
    expect($payment->fresh()->status)->toBe('failed');
    expect($product->fresh()->stock_reserved)->toBe(0);
    expect(\App\Models\Event::where('event_type', 'cancel')->where('reason', 'expired')->count())->toBe(1);

    Carbon::setTestNow();
});

// ---- Driver / adapter ----

test('PAY-57 an empty or unknown driver is rejected with a clear message', function () {
    expect(fn () => ProviderRegistry::validateDriver(''))->toThrow(\LogicException::class, 'PAYMENT_DRIVER');
    expect(fn () => ProviderRegistry::validateDriver('stripe'))->toThrow(\LogicException::class, 'PAYMENT_DRIVER');
    expect(ProviderRegistry::validateDriver('simulator'))->toBe('simulator');
    expect(ProviderRegistry::isKnownProvider('simulator'))->toBeTrue();
    expect(ProviderRegistry::isKnownProvider('stripe'))->toBeFalse();
});

test('PAY-57 resolving the adapter with an unknown driver fails', function () {
    config()->set('payment.driver', 'bogus');

    expect(fn () => app(PaymentAdapter::class))->toThrow(\LogicException::class, 'PAYMENT_DRIVER');
});

test('PAY-57 the application fails to start with an unknown PAYMENT_DRIVER', function () {
    // JANGAN uji lewat reboot + env: dua kali gagal dengan dua cara
    // berbeda (putenv tidak mengubah $_ENV; reboot tetap membaca
    // 'simulator' entah dari mana) membuktikan pendekatan itu rapuh —
    // hasilnya tergantung state proses/.env/config cache di mesin,
    // bukan tergantung kode. Yang diuji di sini adalah mekanisme yang
    // sebenarnya: boot() provider memanggil validateDriver() dengan
    // nilai dari config — driver tidak dikenal → LogicException.
    // (Validasi env→config adalah tugas Laravel; dua tes PAY-57 lain
    // sudah membuktikan validateDriver + resolusi adapter.)
    config()->set('payment.driver', 'bogus');

    $provider = new \App\Providers\AppServiceProvider($this->app);

    expect(fn () => $provider->boot())->toThrow(\LogicException::class, 'PAYMENT_DRIVER');
});

test('PAY-55 checkout and order code never refer to the simulator adapter class directly', function () {
    foreach ([
        'app/Services/CheckoutService.php',
        'app/Services/OrderExpiryService.php',
        'app/Http/Controllers/Shop/CheckoutController.php',
        'app/Http/Controllers/Shop/OrderController.php',
        'app/Http/Controllers/Shop/PaymentInstructionController.php',
    ] as $path) {
        $source = file_get_contents(base_path($path));
        expect($source)->not->toContain('SimulatorPaymentAdapter');
    }
});

// ---- Traces ----

test('PAY-44 traces never contain the webhook secret or the app key', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    // A signed callback (valid) and a rejected one (bad signature).
    m3bWebhook($this, m3bPaidPayload($payment))->assertOk();
    m3bWebhook($this, m3bPaidPayload($payment), 'wrong')->assertStatus(401);

    $appKey = (string) config('app.key');

    foreach (PaymentHttpTrace::all() as $trace) {
        $json = json_encode([
            $trace->request_headers_json, $trace->request_body_json,
            $trace->response_body_json,
        ]);
        expect($json)->not->toContain('test-secret');
        expect($json)->not->toContain('sim-hmac-sha256=');

        if ($appKey !== '') {
            expect($json)->not->toContain($appKey);
        }
    }

    // The signature header name is recorded (Symfony lowercases
    // header names), its value is not.
    $trace = PaymentHttpTrace::where('kind', 'callback')->first();
    expect($trace->request_headers_json)->toHaveKey('x-simulator-signature');
    expect($trace->request_headers_json['x-simulator-signature'])->toBe('[disembunyikan]');
});

test('PAY-28/39 transitions and their trace share one request_id per callback', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk();

    $transition = $payment->transitions()->where('to_status', 'paid')->first();
    expect($transition)->not->toBeNull();
    expect($transition->request_id)->not->toBeNull();

    $trace = PaymentHttpTrace::where('payment_id', $payment->id)
        ->where('kind', 'callback')->first();
    expect($trace)->not->toBeNull();
    expect($trace->request_id)->toBe($transition->request_id);
});

test('PAY-36 the outer request survives an internal dispatch unchanged', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    // The buyer session from the outer request must still be alive
    // after the sim action dispatched the webhook internally (B-01).
    $this->actingAs($buyer, 'web');
    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();
    $this->get('/pesanan/'.$order->id)->assertOk()->assertSee($order->order_number);
});
