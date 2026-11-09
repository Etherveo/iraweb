<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

// Jadwal pengambilan (keputusan Ren 2026-11-09): checkout wajib membawa
// tanggal+jam pengambilan (WITA); disimpan UTC di orders.pickup_at;
// tampil sebagai instruksi ke pembeli dan baris di detail admin.
// Pesanan tanpa jadwal (NULL — pesanan lama/simulator) tidak menampilkan
// apa pun. Tidak ada Python, tidak ada HTTP eksternal.
// Helper lokal mengikuti pola m3c* di PaymentM3cTest.

function psBuyer(): User
{
    return User::factory()->create();
}

function psAdmin(): User
{
    return User::factory()->admin()->create();
}

function psProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['stock_on_hand' => 10, 'stock_reserved' => 0, 'price_idr' => 5000],
        $attributes,
    ));
}

// Pickup valid: besok pukul 10:00 WITA.
function psPickup(): array
{
    return [
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '10:00',
    ];
}

function psCheckout($t, User $buyer, array $lines, string $method = 'qris', ?string $key = null, ?array $pickup = null): Order
{
    $t->actingAs($buyer, 'web');

    foreach ($lines as $line) {
        $t->post('/keranjang/tambah', [
            'product_id' => $line['product']->id,
            'quantity' => $line['qty'],
        ])->assertRedirect('/keranjang');
    }

    $t->post('/checkout', array_merge([
        'payment_method' => $method,
        'checkout_key' => $key ?? (string) Str::uuid(),
    ], $pickup ?? psPickup()))->assertRedirect();

    return Order::where('user_id', $buyer->id)->latest('id')->first();
}

function psPaidOrder($t, User $buyer, ?array $pickup = null): Order
{
    $order = psCheckout($t, $buyer, [['product' => psProduct(), 'qty' => 1]], 'qris', null, $pickup);
    $payment = $order->payments()->first();
    $t->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertOk();

    return $order->fresh();
}

test('checkout page shows the pickup date and time fields', function () {
    $buyer = psBuyer();
    $product = psProduct();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');

    $this->get('/checkout')->assertOk()
        ->assertSee('Jadwal pengambilan')
        ->assertSee('Tanggal pengambilan')
        ->assertSee('Jam pengambilan');
});

test('checkout without a pickup schedule is rejected', function () {
    $buyer = psBuyer();
    $product = psProduct();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');

    $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['pickup_date', 'pickup_time']);

    $this->assertDatabaseCount('orders', 0);
});

test('checkout with a past pickup date is rejected', function () {
    $buyer = psBuyer();
    $product = psProduct();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');

    $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
        'pickup_date' => Carbon::now('Asia/Makassar')->subDay()->toDateString(),
        'pickup_time' => '10:00',
    ])->assertSessionHasErrors('pickup_date');

    $this->assertDatabaseCount('orders', 0);
});

test('checkout with today and a past pickup time is rejected', function () {
    $buyer = psBuyer();
    $product = psProduct();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');

    $now = Carbon::now('Asia/Makassar');
    $pastTime = $now->hour === 0 ? '00:00' : $now->copy()->subHour()->format('H:i');

    $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
        'pickup_date' => $now->toDateString(),
        'pickup_time' => $pastTime,
    ])->assertSessionHasErrors('pickup_time');

    $this->assertDatabaseCount('orders', 0);
});

test('checkout stores pickup_at as the UTC conversion of the WITA input', function () {
    $buyer = psBuyer();
    $product = psProduct();
    $pickup = psPickup();

    $order = psCheckout($this, $buyer, [['product' => $product, 'qty' => 1]], 'qris', null, $pickup);

    $expectedUtc = Carbon::createFromFormat('Y-m-d H:i', $pickup['pickup_date'].' '.$pickup['pickup_time'], 'Asia/Makassar')
        ->setTimezone('UTC');

    expect($order->fresh()->pickup_at)->not->toBeNull();
    expect($order->fresh()->pickup_at->eq($expectedUtc))->toBeTrue();
    expect($order->fresh()->pickup_at->timezone->getName())->toBe('UTC');

    // The instruction page (redirect target) shows the pickup instruction.
    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertOk()
        ->assertSee('Pengambilan di toko')
        ->assertSee($expectedUtc->copy()->setTimezone('Asia/Makassar')->format('d M Y'));
});

test('pickup instruction shows on result, receipt and buyer detail when set', function () {
    $buyer = psBuyer();
    $order = psPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get(route('pembayaran.hasil', $payment))->assertOk()
        ->assertSee('Pembayaran Berhasil')
        ->assertSee('Pengambilan di toko');
    $this->get(route('pembayaran.struk', $payment))->assertOk()
        ->assertSee('Pengambilan di toko');
    $this->get('/pesanan/'.$order->id)->assertOk()
        ->assertSee('Pengambilan di toko');

    // Admin sees the schedule row on the order detail.
    $admin = psAdmin();
    $this->actingAs($admin, 'web');
    $this->get('/admin/pesanan/'.$order->id)->assertOk()
        ->assertSee('Jadwal pengambilan');
});

test('pickup instruction and admin row are hidden when pickup_at is NULL', function () {
    $buyer = psBuyer();
    $order = psPaidOrder($this, $buyer);
    $payment = $order->payments()->first();
    $order->update(['pickup_at' => null]);

    $this->actingAs($buyer, 'web');
    $this->get(route('pembayaran.hasil', $payment))->assertOk()
        ->assertDontSee('Pengambilan di toko');
    $this->get(route('pembayaran.struk', $payment))->assertOk()
        ->assertDontSee('Pengambilan di toko');
    $this->get('/pesanan/'.$order->id)->assertOk()
        ->assertDontSee('Pengambilan di toko');

    // Instruction page (unpaid order): a NULL schedule shows nothing.
    // A paid order redirects away from the instruction page, so this
    // uses a second, still-waiting order.
    $waiting = psCheckout($this, $buyer, [['product' => psProduct(), 'qty' => 1]]);
    $waiting->update(['pickup_at' => null]);
    $this->get('/pesanan/'.$waiting->id.'/bayar')->assertOk()
        ->assertDontSee('Pengambilan di toko');

    $admin = psAdmin();
    $this->actingAs($admin, 'web');
    $this->get('/admin/pesanan/'.$order->id)->assertOk()
        ->assertDontSee('Jadwal pengambilan');
});
