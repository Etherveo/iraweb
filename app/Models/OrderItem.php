<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M3a ORD-02/ORD-04, blueprint §6.2. unit_price_idr and
// product_name_snapshot freeze the product at checkout time, so a later
// price/name change never rewrites an old order (ORD-04, EVT-07).
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'product_name_snapshot',
        'unit_price_idr', 'quantity', 'subtotal_idr',
        'origin', 'simulation_run_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_idr' => 'integer',
            'quantity' => 'integer',
            'subtotal_idr' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function formattedUnitPrice(): string
    {
        return 'Rp '.number_format((int) $this->unit_price_idr, 0, ',', '.');
    }

    public function formattedSubtotal(): string
    {
        return 'Rp '.number_format((int) $this->subtotal_idr, 0, ',', '.');
    }
}
