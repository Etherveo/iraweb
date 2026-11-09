<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// M2 PRD-01..07, STK-01, blueprint §6.2.
// stock_available is DERIVED (stock_on_hand - stock_reserved), never a
// stored column, so it cannot drift from the two stored values.
// The DB CHECK (stock_reserved <= stock_on_hand) guarantees it is >= 0.
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'description', 'price_idr',
        'stock_on_hand', 'stock_reserved', 'is_active', 'release_date',
    ];

    protected function casts(): array
    {
        return [
            'price_idr' => 'integer',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
            'is_active' => 'boolean',
            'release_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    protected function stockAvailable(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, (int) $this->stock_on_hand - (int) $this->stock_reserved),
        );
    }

    public function formattedPrice(): string
    {
        return 'Rp '.number_format((int) $this->price_idr, 0, ',', '.');
    }
}
