<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M3a EVT-01..09, blueprint §6.2. Append-only: the app never updates
// or deletes events (only the M4 simulation reset may, SIM-38/39).
// Writes go only through ActivityRecorder, never directly.
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const TYPES = ['view', 'add_to_cart', 'booking', 'purchase', 'cancel'];

    protected $fillable = [
        'event_type', 'user_id', 'product_id', 'session_ref',
        'order_id', 'order_item_id', 'reason', 'occurred_at',
        'origin', 'simulation_run_id',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
