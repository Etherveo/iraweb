<?php

namespace App\Services;

use App\Models\Developer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// M2 PRD-05 / PRD-01 / PRD-07, STK-01 / STK-05; M3a STK-02..06.
// reserve/confirm/release run inside the caller's transaction (checkout,
// payment, cancel, expiry): they never open one of their own, so the
// stock change, the order/payment change, the movement row and the
// events all commit or roll back together (STK-05).
//
// Anti-oversell (STK-06, blueprint §7.2): every stock change is a
// CONDITIONAL update — `stock_on_hand - stock_reserved >= qty` for a
// reservation, `stock_reserved >= qty` for confirm/release — inside one
// DB transaction, and 0 affected rows is a failure (NFR-01). SQLite has
// no FOR UPDATE / SKIP LOCKED, so the conditional update is the guard;
// it is atomic because the UPDATE itself is the check. (A literal
// BEGIN IMMEDIATE is not issued: Laravel owns the PDO transaction and
// a manual BEGIN would break the test transaction wrapper; the
// conditional update gives the same observable guarantee.)
class StockService
{
    // PRD-01: when a product is created with initial stock > 0, exactly
    // one 'penyesuaian' movement is written in the same transaction as
    // the product (the caller owns the transaction). Stock 0 => no row.
    public function recordInitialStock(Product $product, User|Developer $actor): void
    {
        $initial = (int) $product->stock_on_hand;

        if ($initial <= 0) {
            return;
        }

        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'penyesuaian',
            'delta_on_hand' => $initial,
            'delta_reserved' => 0,
            'order_id' => null,
            'actor_type' => $actor instanceof Developer ? 'developer' : 'admin_toko',
            'actor_id' => $actor->id,
            'note' => 'Stok awal produk: 0 → '.$initial.'.',
            'origin' => 'normal',
            'simulation_run_id' => null,
            'created_at' => now(),
        ]);
    }

    // PRD-05: set a new physical stock, atomically with one movement.
    // The new value may never be below stock_reserved; that failure is
    // a validation error (back to the form), not a partial write: the
    // check and the write run inside one transaction, and a zero-row
    // conditional update would also fail loudly (NFR-01 pattern, M1).
    public function adjust(Product $product, int $newOnHand, User $actor, ?string $note = null): void
    {
        $reserved = (int) $product->stock_reserved;

        if ($newOnHand < $reserved) {
            throw ValidationException::withMessages([
                'stock_on_hand' => 'Stok fisik baru tidak boleh lebih kecil dari stok dicadangkan ('.$reserved.').',
            ]);
        }

        DB::transaction(function () use ($product, $newOnHand, $actor, $note) {
            $old = (int) $product->fresh()->stock_on_hand;

            $affected = Product::query()
                ->whereKey($product->id)
                ->update(['stock_on_hand' => $newOnHand, 'updated_at' => now()]);

            if ($affected !== 1) {
                throw ValidationException::withMessages([
                    'stock_on_hand' => 'Produk tidak ditemukan atau stok gagal diperbarui.',
                ]);
            }

            $text = 'Penyesuaian stok: '.$old.' → '.$newOnHand.'.';
            if ($note !== null && trim($note) !== '') {
                $text .= ' Alasan: '.trim($note);
            }

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'penyesuaian',
                'delta_on_hand' => $newOnHand - $old,
                'delta_reserved' => 0,
                'order_id' => null,
                'actor_type' => 'admin_toko',
                'actor_id' => $actor->id,
                'note' => $text,
                'origin' => 'normal',
                'simulation_run_id' => null,
                'created_at' => now(),
            ]);
        });
    }

    // STK-02: reserve stock for an order (checkout). Items are processed
    // in product-id order (blueprint §7 access order). One item failing
    // the conditional update aborts the whole checkout via exception,
    // so the caller's transaction rolls back with no data change.
    public function reserve(Order $order, string $actorType, ?int $actorId): void
    {
        foreach ($order->items()->orderBy('product_id')->get() as $item) {
            $quantity = (int) $item->quantity;

            $affected = Product::query()
                ->whereKey($item->product_id)
                ->whereRaw('stock_on_hand - stock_reserved >= ?', [$quantity])
                ->update([
                    'stock_reserved' => DB::raw('stock_reserved + '.$quantity),
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                throw ValidationException::withMessages([
                    'keranjang' => 'Stok produk “'.$item->product_name_snapshot.'” tidak mencukupi untuk jumlah yang dipesan.',
                ]);
            }

            $this->writeMovement($item->product_id, 'cadangkan', 0, $quantity, $order, $actorType, $actorId,
                'Cadangkan stok untuk pesanan '.$order->order_number.'.');
        }
    }

    // STK-03: confirm stock when a payment becomes paid. Reserved and
    // physical both decrease by qty. The caller (PaymentStateMachine)
    // only calls this once per order — the payment is final afterwards,
    // so a second paid call is rejected before it reaches here — and
    // the conditional update would fail loudly if it ever were.
    public function confirm(Order $order, string $actorType, ?int $actorId): void
    {
        foreach ($order->items()->orderBy('product_id')->get() as $item) {
            $quantity = (int) $item->quantity;

            $affected = Product::query()
                ->whereKey($item->product_id)
                ->where('stock_reserved', '>=', $quantity)
                ->where('stock_on_hand', '>=', $quantity)
                ->update([
                    'stock_reserved' => DB::raw('stock_reserved - '.$quantity),
                    'stock_on_hand' => DB::raw('stock_on_hand - '.$quantity),
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                throw ValidationException::withMessages([
                    'stok' => 'Stok produk “'.$item->product_name_snapshot.'” tidak dapat dikonfirmasi.',
                ]);
            }

            $this->writeMovement($item->product_id, 'konfirmasi', -$quantity, -$quantity, $order, $actorType, $actorId,
                'Konfirmasi stok untuk pesanan '.$order->order_number.' (terbayar).');
        }
    }

    // STK-04: release reserved stock on cancel/expiry. Exactly once per
    // order: the caller only reaches this from menunggu_pembayaran, and
    // a paid order never releases (its reservation was already
    // confirmed away). A failed payment releases nothing (no call).
    public function release(Order $order, string $actorType, ?int $actorId): void
    {
        foreach ($order->items()->orderBy('product_id')->get() as $item) {
            $quantity = (int) $item->quantity;

            $affected = Product::query()
                ->whereKey($item->product_id)
                ->where('stock_reserved', '>=', $quantity)
                ->update([
                    'stock_reserved' => DB::raw('stock_reserved - '.$quantity),
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                throw ValidationException::withMessages([
                    'stok' => 'Stok produk “'.$item->product_name_snapshot.'” tidak dapat dilepas.',
                ]);
            }

            $this->writeMovement($item->product_id, 'lepas', 0, -$quantity, $order, $actorType, $actorId,
                'Lepas cadangan stok untuk pesanan '.$order->order_number.'.');
        }
    }

    private function writeMovement(
        int $productId,
        string $type,
        int $deltaOnHand,
        int $deltaReserved,
        Order $order,
        string $actorType,
        ?int $actorId,
        string $note,
    ): void {
        StockMovement::create([
            'product_id' => $productId,
            'type' => $type,
            'delta_on_hand' => $deltaOnHand,
            'delta_reserved' => $deltaReserved,
            'order_id' => $order->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note,
            'origin' => $order->origin,
            'simulation_run_id' => $order->simulation_run_id,
            'created_at' => now(),
        ]);
    }
}
