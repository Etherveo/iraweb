<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// M2 PRD-05 / PRD-01 / PRD-07, STK-01 / STK-05.
// ONLY 'penyesuaian' movements are written in M2. STK-02..06
// (cadangkan/konfirmasi/lepas) are M3 and must NOT be added here;
// the stock_movements type CHECK already allows them so M3 needs no
// schema change, only new service methods in M3.
class StockService
{
    // PRD-01: when a product is created with initial stock > 0, exactly
    // one 'penyesuaian' movement is written in the same transaction as
    // the product (the caller owns the transaction). Stock 0 => no row.
    public function recordInitialStock(Product $product, User $actor): void
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
            'actor_type' => 'admin_toko',
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
}
