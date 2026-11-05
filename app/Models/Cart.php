<?php

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// M3a CRT-01..06, blueprint §6.2. One cart per account (unique user_id);
// the cart itself is never deleted by the app, only its items are.
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Total quantity across items (CRT-04: shown in the header).
    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    // Server-computed total (CRT-04): current unit price x quantity.
    public function totalIdr(): int
    {
        return (int) $this->items->sum(fn (CartItem $item) => $item->subtotalIdr());
    }
}
