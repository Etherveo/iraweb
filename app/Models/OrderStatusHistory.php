<?php

namespace App\Models;

use Database\Factories\OrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M3a ORD-14, blueprint §6.2. Append-only: no updated_at column and no
// update/delete code path anywhere. from_status is null for the initial
// row (null -> menunggu_pembayaran at checkout).
class OrderStatusHistory extends Model
{
    /** @use HasFactory<OrderStatusHistoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id', 'from_status', 'to_status', 'trigger',
        'actor_type', 'actor_id', 'reason', 'occurred_at',
        'origin', 'simulation_run_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
