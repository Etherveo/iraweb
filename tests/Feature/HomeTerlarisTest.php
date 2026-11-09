<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentStateMachine;
use Carbon\Carbon;
use Illuminate\Support\Str;

// Terlaris — Keputusan Ren 2026-11-09 (kalkulasi-terlaris.md): threshold
// 3 unit bersih dalam 30 hari terakhir (orders.paid_at, UTC). Pesanan batal
// tidak pernah mencapai dibayar, jadi tidak terhitung; pengembalian dana
// tidak ada di sistem ini. Tidak ada Python, tidak ada HTTP eksternal.
// Helper lokal mengikuti pola m3* di OrderM3aTest (helper di sana tidak
// bisa dipakai lintas file bila file ini dijalankan sendiri).

function htBuyer(): User
{
    return User::factory()->create();
}

function htProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['stock_on_hand' => 10, 'stock_reserved' => 0, 'price_idr' => 5000],
        $attributes,
    ));
}

function htCheckout($t, User $buyer, array $lines, string $method = 'qris', ?string $key = null): Order
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
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '10:00',
    ])->assertRedirect();

    return Order::where('user_id', $buyer->id)->latest('id')->first();
}

function htPaidOrder($t, User $buyer, array $lines): Order
{
    $order = htCheckout($t, $buyer, $lines);
    $payment = $order->payments()->first();
    app(PaymentStateMachine::class)->markPaid($payment);

    return $order->fresh();
}

test('Terlaris shows a product with 3 paid units in the last 30 days', function () {
    $buyer = htBuyer();
    $product = htProduct(['name' => 'Pulpen Terlaris Tiga']);
    htPaidOrder($this, $buyer, [['product' => $product, 'qty' => 3]]);

    $this->get('/')->assertOk()
        ->assertSee('Terlaris')
        ->assertSee('Pulpen Terlaris Tiga');
});

test('Terlaris hides the section when the best product sold only 2 paid units', function () {
    $buyer = htBuyer();
    $product = htProduct(['name' => 'Pulpen Terlaris Dua']);
    htPaidOrder($this, $buyer, [['product' => $product, 'qty' => 2]]);

    $this->get('/')->assertOk()
        ->assertDontSee('Terlaris');
});

test('Terlaris ignores paid units older than 30 days', function () {
    $buyer = htBuyer();
    $product = htProduct(['name' => 'Pulpen Terlaris Lama']);
    $order = htPaidOrder($this, $buyer, [['product' => $product, 'qty' => 5]]);
    $order->update(['paid_at' => now()->subDays(40)]);

    $this->get('/')->assertOk()
        ->assertDontSee('Terlaris');
});

test('Terlaris ignores unpaid orders', function () {
    $buyer = htBuyer();
    $product = htProduct(['name' => 'Pulpen Terlaris Belum Bayar']);
    htCheckout($this, $buyer, [['product' => $product, 'qty' => 5]]);

    $this->get('/')->assertOk()
        ->assertDontSee('Terlaris');
});
