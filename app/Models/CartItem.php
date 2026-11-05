<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M3a CRT-01..03, blueprint §6.2. Quantity is 1..99 (DB CHECK);
// uniqueness (cart_id, product_id) makes "add same product" a merge.
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected $fillable = ['cart_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Current unit price x quantity (CRT-04). The price is read live
    // from the product here; an order freezes it as a snapshot (ORD-04).
    public function subtotalIdr(): int
    {
        return (int) $this->product->price_idr * (int) $this->quantity;
    }

    // CRT-05: an item blocks checkout when its product is inactive or
    // its available stock is below the quantity in the cart.
    public function isBlocked(): bool
    {
        return ! $this->product->is_active
            || $this->product->stock_available < (int) $this->quantity;
    }

    public function blockedReason(): ?string
    {
        if (! $this->product->is_active) {
            return 'Produk sudah tidak aktif.';
        }

        if ($this->product->stock_available < (int) $this->quantity) {
            return 'Stok tersedia ('.$this->product->stock_available.') kurang dari jumlah di keranjang ('.$this->quantity.').';
        }

        return null;
    }
}
