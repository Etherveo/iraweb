<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentHttpTrace;
use App\Models\PaymentStatusTransition;
use App\Models\Product;
use App\Models\User;
use App\Payments\SignatureVerifier;
use App\Services\PaymentTracePanel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// M3c PAY-37..54. HTTP panel (read-only, from stored traces),
// timeline, copy JSON/cURL, result pages, receipt, buyer history +
// detail, admin list + detail, PAY-53 (no payment mutation routes),
// PAY-54 (SIMULASI label on every payment view). No Python, no
// Figma images, no external HTTP: every provider event goes through
// the app's own webhook endpoint via InternalDispatcher.
//
// The webhook secret for tests is set via config()->set here, never
// in phpunit.xml or .env. app.url is pinned so the panel/cURL URLs
// do not depend on the developer's local .env.

beforeEach(function () {
    config()->set('payment.webhook_secret', 'test-secret');
    config()->set('app.url', 'http://localhost:8000');
});

function m3cBuyer(): User
{
    return User::factory()->create();
}

function m3cAdmin(): User
{
    return User::factory()->admin()->create();
}

function m3cProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['stock_on_hand' => 10, 'stock_reserved' => 0, 'price_idr' => 5000],
        $attributes,
    ));
}

// Build a cart through the real HTTP routes, then check out. Returns
// the created order (fresh). The checkout POST redirects to the
// payment instruction page (M3b).
function m3cCheckout($t, User $buyer, array $lines, string $method = 'qris', ?string $key = null): Order
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

function m3cPaidOrder($t, User $buyer): Order
{
    $order = m3cCheckout($t, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();
    $t->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();

    return $order->fresh();
}

// ---- Panel: PAY-37/38/39 ----

test('PAY-37 panel shows the stored create_payment request and response', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee('Simulated HTTP Request')
        ->assertSee('/api/v1/simulator/payments')
        ->assertSee($order->order_number)
        ->assertSee('IDR')
        ->assertSee('qris')
        ->assertSee('callback_url')
        ->assertSee('return_url')
        ->assertSee('payment_token')
        ->assertSee('qr_url')
        ->assertSee('expires_at')
        ->assertSee('HTTP 201');
});

test('PAY-38 panel shows the callback: method/path, dummy signature header, body, response and order status after', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee('/api/v1/webhooks/payments/simulator')
        ->assertSee('X-Simulator-Signature')
        ->assertSee('dummy')
        ->assertSee('Content-Type')
        ->assertSee('paid_at')
        ->assertSee('HTTP 200')
        ->assertSee('Status pesanan setelah callback:')
        ->assertSee('Dibayar');
});

test('PAY-39 timeline shows transitions chronologically with trigger and request id', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $paidTransition = PaymentStatusTransition::query()
        ->where('payment_id', $payment->id)->where('to_status', 'paid')->first();
    expect($paidTransition)->not->toBeNull();
    expect($paidTransition->request_id)->not->toBeNull();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee('Timeline Transisi Status')
        ->assertSee('checkout')
        ->assertSee('callback_simulasi')
        ->assertSee('Menunggu Pembayaran')
        ->assertSee('Terbayar')
        ->assertSee('WITA')
        ->assertSee($paidTransition->request_id);
});

// ---- Panel: PAY-40/41/42/43/44 ----

test('PAY-40 copy buttons exist for each request/response JSON and for cURL, targeting the exact text shown', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();
    $create = PaymentHttpTrace::query()->where('payment_id', $payment->id)->where('kind', 'create_payment')->first();
    $callback = PaymentHttpTrace::query()->where('payment_id', $payment->id)->where('kind', 'callback')->first();
    expect($create)->not->toBeNull();
    expect($callback)->not->toBeNull();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee('data-copy-target="trace-'.$create->id.'-request"', false)
        ->assertSee('data-copy-target="trace-'.$create->id.'-response"', false)
        ->assertSee('data-copy-target="trace-'.$callback->id.'-request"', false)
        ->assertSee('data-copy-target="trace-'.$callback->id.'-response"', false)
        ->assertSee('id="trace-'.$create->id.'-request"', false)
        ->assertSee('id="trace-'.$callback->id.'-response"', false)
        ->assertSee('data-copy-target="trace-curl"', false)
        ->assertSee('Salin JSON request')
        ->assertSee('Salin JSON response')
        ->assertSee('Salin cURL');
});

test('PAY-41 cURL callback uses the absolute URL, both headers and a valid HMAC for exactly the body shown', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $trace = PaymentHttpTrace::query()->where('payment_id', $payment->id)->where('kind', 'callback')->first();
    expect($trace)->not->toBeNull();

    $curl = (new PaymentTracePanel)->curlForCallback($trace);
    expect($curl)->toContain("curl -X POST 'http://localhost:8000/api/v1/webhooks/payments/simulator'");
    expect($curl)->toContain("-H 'Content-Type: application/json'");
    expect($curl)->toContain("-H 'X-Simulator-Signature: sim-hmac-sha256=");
    expect($curl)->not->toContain('test-secret');

    // The signature must verify against EXACTLY the body in the command.
    preg_match("/-d '(\\{.*\\})'/", $curl, $bodyMatch);
    preg_match('/X-Simulator-Signature: (sim-hmac-sha256=[0-9a-f]{64})/', $curl, $sigMatch);
    expect($bodyMatch[1] ?? null)->not->toBeNull();
    expect($sigMatch[1] ?? null)->not->toBeNull();
    expect(SignatureVerifier::verify($bodyMatch[1], $sigMatch[1], 'test-secret'))->toBeTrue();

    // And the page shows the same command.
    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()->assertSee('curl -X POST');
});

test('PAY-42 panel JSON is escaped safely and the detail page has no payment-mutation control', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    PaymentHttpTrace::factory()->create([
        'payment_id' => $payment->id,
        'order_id' => $order->id,
        'kind' => 'callback',
        'request_body_json' => ['payment_id' => $payment->id, 'note' => '<script>alert(1)</script>'],
        'response_body_json' => ['result' => 'processed', 'payment_status' => 'paid', 'order_status' => 'dibayar'],
    ]);

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('Ubah status pembayaran')
        ->assertDontSee('name="status"', false);
});

test('PAY-43 payment without HTTP traces shows the developer-panel note', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    PaymentHttpTrace::query()->where('payment_id', $payment->id)->delete();
    PaymentStatusTransition::factory()->create([
        'payment_id' => $payment->id,
        'from_status' => 'pending',
        'to_status' => 'paid',
        'trigger' => 'panel_developer',
        'occurred_at' => now(),
    ]);

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee('Dibuat oleh panel developer — tanpa lalu lintas HTTP')
        ->assertDontSee('Pembuatan pembayaran');
});

test('PAY-44 stored traces and the rendered panel never contain the webhook secret or the app key', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $secret = 'test-secret';
    $appKey = (string) config('app.key');

    $traces = PaymentHttpTrace::query()->where('payment_id', $payment->id)->get();
    expect($traces)->not->toBeEmpty();

    foreach ($traces as $trace) {
        $dump = (string) json_encode([$trace->request_headers_json, $trace->request_body_json, $trace->response_body_json]);
        expect($dump)->not->toContain($secret);
        expect($dump)->not->toContain('sim-hmac-sha256=');
        if ($appKey !== '') {
            expect($dump)->not->toContain($appKey);
        }
    }

    // The rendered panel may show the cURL with its HMAC (PAY-41),
    // but never the secret itself (nor the app key).
    $this->actingAs($buyer, 'web');
    $response = $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertDontSee($secret);
    if ($appKey !== '') {
        $response->assertDontSee($appKey);
    }
});

test('PAY-15/37 simulator page shows the HTTP panel for the token holder', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->get('/pay/sim/'.$payment->payment_token)->assertOk()
        ->assertSee('Simulated HTTP Request')
        ->assertSee('/api/v1/simulator/payments')
        ->assertSee('SIMULASI');
});

// ---- Result pages + polling: PAY-13/45/46/47 ----

test('PAY-45 result page for a paid payment shows the summary and links to trace and receipt', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/hasil')->assertOk()
        ->assertSee('Pembayaran Berhasil')
        ->assertSee('SIMULASI')
        ->assertSee($order->order_number)
        ->assertSee($order->formattedTotal())
        ->assertSee('Lihat jejak HTTP')
        ->assertSee('Lihat struk');
});

test('PAY-46 failed result page offers repay with another method and cancel while allowed', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();
    $this->post('/pay/sim/'.$payment->payment_token.'/gagal')->assertOk();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/hasil')->assertOk()
        ->assertSee('Pembayaran Gagal')
        ->assertSee('SIMULASI')
        ->assertSee($order->order_number)
        ->assertSee('Buat Pembayaran Baru')
        ->assertSee('Batalkan Pesanan')
        ->assertSee('name="payment_method"', false);
});

test('PAY-46 failed result page after the deadline offers no repay or cancel', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();
    $this->post('/pay/sim/'.$payment->payment_token.'/gagal')->assertOk();

    try {
        Carbon::setTestNow(now()->addMinutes(31));

        $this->actingAs($buyer, 'web');
        $this->get('/pembayaran/'.$payment->id.'/hasil')->assertOk()
            ->assertSee('Pembayaran Gagal')
            ->assertDontSee('Buat Pembayaran Baru')
            ->assertDontSee('Batalkan Pesanan');
    } finally {
        Carbon::setTestNow();
    }
});

test('PAY-47 expired result page has no pay action and links to order history and catalog', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    try {
        Carbon::setTestNow(now()->addMinutes(31));

        $this->actingAs($buyer, 'web');
        $this->get('/pembayaran/'.$payment->id.'/hasil')->assertOk()
            ->assertSee('Pembayaran Kedaluwarsa')
            ->assertSee('SIMULASI')
            ->assertSee($order->order_number)
            ->assertDontSee('Buat Pembayaran Baru')
            ->assertSee('Lihat riwayat pesanan')
            ->assertSee('Kembali ke katalog');
    } finally {
        Carbon::setTestNow();
    }
});

test('PAY-45/47 result page redirects a pending payment to the instruction page and a cancelled one to the order detail', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/hasil')->assertRedirect(route('pesanan.bayar', $order));

    $this->post('/pesanan/'.$order->id.'/batal')->assertRedirect(route('pesanan.show', $order));
    $this->get('/pembayaran/'.$payment->id.'/hasil')->assertRedirect(route('pesanan.show', $order));
});

test('PAY-13 polling redirects to the result page for paid/failed/expired and to the order detail for cancelled', function () {
    $buyer = m3cBuyer();

    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertOk()
        ->assertJsonPath('redirect_url', null);

    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();
    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertOk()
        ->assertJsonPath('redirect_url', route('pembayaran.hasil', $payment));

    $order2 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $failed = $order2->payments()->first();
    $this->post('/pay/sim/'.$failed->payment_token.'/gagal')->assertOk();
    $this->getJson('/pembayaran/'.$failed->id.'/status')->assertOk()
        ->assertJsonPath('redirect_url', route('pembayaran.hasil', $failed));

    $order3 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $expired = $order3->payments()->first();

    try {
        Carbon::setTestNow(now()->addMinutes(31));
        $this->getJson('/pembayaran/'.$expired->id.'/status')->assertOk()
            ->assertJson(['status' => 'expired'])
            ->assertJsonPath('redirect_url', route('pembayaran.hasil', $expired));
    } finally {
        Carbon::setTestNow();
    }

    $order4 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $cancelled = $order4->payments()->first();
    $this->post('/pesanan/'.$order4->id.'/batal')->assertRedirect(route('pesanan.show', $order4));
    $this->getJson('/pembayaran/'.$cancelled->id.'/status')->assertOk()
        ->assertJson(['status' => 'cancelled'])
        ->assertJsonPath('redirect_url', route('pesanan.show', $order4));
});

// ---- Receipt: PAY-48 ----

test('PAY-48 receipt for a paid payment shows items, total, method, paid time and the printable SIMULASI label', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(['name' => 'Pulpen Struk']), 'qty' => 2]]);
    $payment = $order->payments()->first();
    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/struk')->assertOk()
        ->assertSee('STRUK SIMULASI — bukan bukti pembayaran sah')
        ->assertSee('Cetak Struk')
        ->assertSee($order->order_number)
        ->assertSee('Pulpen Struk')
        ->assertSee($order->formattedTotal())
        ->assertSee('QRIS (Simulasi)');
});

test('PAY-48 receipt redirects a non-paid payment to its result page and is owner-only', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/struk')->assertRedirect(route('pembayaran.hasil', $payment));

    $this->actingAs(m3cBuyer(), 'web');
    $this->get('/pembayaran/'.$payment->id.'/struk')->assertNotFound();

    $this->get('/pembayaran/'.$payment->id.'/struk')->assertRedirect(route('masuk'));
});

// ---- Buyer history + detail: PAY-49/50, ORD-09 ----

test('PAY-49 buyer payment history lists only own payments, filters by status, and has an empty state', function () {
    $buyer = m3cBuyer();
    $other = m3cBuyer();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran')->assertOk()->assertSee('Belum ada pembayaran.');

    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $paid = $order->payments()->first();
    $this->post('/pay/sim/'.$paid->payment_token.'/bayar')->assertOk();

    $order2 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $failed = $order2->payments()->first();
    $this->post('/pay/sim/'.$failed->payment_token.'/gagal')->assertOk();

    $otherOrder = m3cCheckout($this, $other, [['product' => m3cProduct(), 'qty' => 1]]);

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran')->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order2->order_number)
        ->assertDontSee($otherOrder->order_number);

    $this->get('/pembayaran?status=paid')->assertOk()
        ->assertSee($order->order_number)
        ->assertDontSee($order2->order_number);

    $this->get('/pembayaran?status=bogus')->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order2->order_number);
});

test('PAY-49 buyer payment history paginates 10 per page', function () {
    $buyer = m3cBuyer();
    $numbers = [];

    for ($i = 0; $i < 11; $i++) {
        $o = Order::factory()->create(['user_id' => $buyer->id]);
        Payment::factory()->create(['order_id' => $o->id, 'user_id' => $buyer->id, 'status' => 'paid']);
        $numbers[] = $o->order_number;
    }

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran')->assertOk()
        ->assertDontSee($numbers[0])
        ->assertSee($numbers[10]);
    $this->get('/pembayaran?page=2')->assertOk()
        ->assertSee($numbers[0])
        ->assertDontSee($numbers[10]);
});

test('PAY-50 payment detail is owner-only: another buyer gets 404, a guest is redirected', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->get('/pembayaran/'.$payment->id)->assertRedirect(route('masuk'));

    $this->actingAs(m3cBuyer(), 'web');
    $this->get('/pembayaran/'.$payment->id)->assertNotFound();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Simulated HTTP Request')
        ->assertSee('Timeline Transisi Status');
});

test('ORD-09 order detail links to the payment detail and its trace', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id)->assertOk()
        ->assertSee(route('pembayaran.show', $payment), false)
        ->assertSee('Lihat detail pembayaran');
});

// ---- Admin: PAY-51/52/53, guards ----

test('PAY-51 admin payment list filters by status, method and date and shows the origin column', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]], 'qris');
    $payment = $order->payments()->first();
    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();

    $order2 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]], 'va_bri');
    $payment2 = $order2->payments()->first();

    $admin = m3cAdmin();
    $this->actingAs($admin, 'web');
    $this->get('/admin/pembayaran')->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order2->order_number)
        ->assertSee('Normal');

    $this->get('/admin/pembayaran?status=paid')->assertOk()
        ->assertSee($order->order_number)
        ->assertDontSee($order2->order_number);

    $this->get('/admin/pembayaran?metode=va_bri')->assertOk()
        ->assertSee($order2->order_number)
        ->assertDontSee($order->order_number);

    $today = now()->format('Y-m-d');
    $this->get('/admin/pembayaran?dari='.$today.'&sampai='.$today)->assertOk()
        ->assertSee($order->order_number);

    $this->get('/admin/pembayaran?dari=2030-01-01')->assertOk()
        ->assertDontSee($order->order_number)
        ->assertSee('Belum ada pembayaran.');
});

test('PAY-52 admin payment detail shows the buyer, the panel and the timeline, read-only', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $admin = m3cAdmin();
    $this->actingAs($admin, 'web');
    $this->get('/admin/pembayaran/'.$payment->id)->assertOk()
        ->assertSee($buyer->name)
        ->assertSee($buyer->email)
        ->assertSee('Simulated HTTP Request')
        ->assertSee('Timeline Transisi Status')
        ->assertSee('Terbayar')
        ->assertDontSee('Ubah status pembayaran')
        ->assertDontSee('name="status"', false);
});

test('M3c guards: a buyer cannot open admin payment pages, a guest is redirected', function () {
    $this->get('/admin/pembayaran')->assertRedirect(route('masuk'));
    $this->get('/pembayaran')->assertRedirect(route('masuk'));

    $buyer = m3cBuyer();
    $this->actingAs($buyer, 'web');
    $this->get('/admin/pembayaran')->assertForbidden();
    $this->get('/admin/pembayaran/xxx')->assertForbidden();
});

test('PAY-53 no admin or developer route can change a payment', function () {
    $admin = m3cAdmin();
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($admin, 'web');
    $this->post('/admin/pembayaran/'.$payment->id.'/status', ['status' => 'paid'])->assertNotFound();
    $this->post('/admin/pembayaran/'.$payment->id.'/bayar')->assertNotFound();
    $this->put('/admin/pembayaran/'.$payment->id, ['status' => 'paid'])->assertNotFound();
    $this->delete('/admin/pembayaran/'.$payment->id)->assertNotFound();
    $this->get('/admin/pembayaran/'.$payment->id.'/ubah')->assertNotFound();

    // Structural: no registered route under the admin/developer
    // payment paths accepts a write method.
    foreach (Route::getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'admin/pembayaran') || str_starts_with($route->uri(), 'developer/pembayaran')) {
            expect(array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()))->toBeEmpty(
                'Route '.$route->uri().' must not accept a write method (PAY-53).'
            );
        }
    }
});

test('M3c guards: every new protected route declares guard and role', function () {
    $routes = Route::getRoutes();

    foreach ([
        '/pembayaran' => 'role:pembeli',
        '/pembayaran/{payment}' => 'role:pembeli',
        '/pembayaran/{payment}/hasil' => 'role:pembeli',
        '/pembayaran/{payment}/struk' => 'role:pembeli',
        '/admin/pembayaran' => 'role:admin_toko',
        '/admin/pembayaran/{payment}' => 'role:admin_toko',
    ] as $uri => $role) {
        $route = collect($routes)->first(fn ($r) => $r->uri() === ltrim($uri, '/'));
        expect($route)->not->toBeNull('Route '.$uri.' must exist.');
        $middleware = $route->gatherMiddleware();
        expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'auth:')))->toBeTrue();
        expect(collect($middleware)->contains(fn ($m) => (string) $m === $role))->toBeTrue();
    }

    $this->assertTrue(true);
});

// ---- PAY-54: SIMULASI label on every payment view ----

test('PAY-54 label SIMULASI appears on every payment view', function () {
    $buyer = m3cBuyer();
    $admin = m3cAdmin();

    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $paid = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertOk()->assertSee('SIMULASI');
    $this->get('/pay/sim/'.$paid->payment_token)->assertOk()->assertSee('SIMULASI');

    $this->post('/pay/sim/'.$paid->payment_token.'/bayar')->assertOk();
    $this->get('/pembayaran/'.$paid->id.'/hasil')->assertOk()->assertSee('SIMULASI');
    $this->get('/pembayaran/'.$paid->id.'/struk')->assertOk()->assertSee('SIMULASI');
    $this->get('/pembayaran')->assertOk()->assertSee('SIMULASI');
    $this->get('/pembayaran/'.$paid->id)->assertOk()->assertSee('SIMULASI');

    $this->actingAs($admin, 'web');
    $this->get('/admin/pembayaran')->assertOk()->assertSee('SIMULASI');
    $this->get('/admin/pembayaran/'.$paid->id)->assertOk()->assertSee('SIMULASI');

    $order2 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $failed = $order2->payments()->first();
    $this->post('/pay/sim/'.$failed->payment_token.'/gagal')->assertOk();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$failed->id.'/hasil')->assertOk()->assertSee('SIMULASI');

    $order3 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $expired = $order3->payments()->first();

    try {
        Carbon::setTestNow(now()->addMinutes(31));
        $this->get('/pembayaran/'.$expired->id.'/hasil')->assertOk()->assertSee('SIMULASI');
    } finally {
        Carbon::setTestNow();
    }
});
