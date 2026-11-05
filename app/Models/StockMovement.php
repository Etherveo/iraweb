<?php

namespace App\Models;

use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M2 PRD-07, STK-05, blueprint §6.2. Append-only: no updated_at column,
// and no update/delete code path exists for this model in M2 (or later:
// M3 writes new rows, it never edits old ones).
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'type', 'delta_on_hand', 'delta_reserved',
        'order_id', 'actor_type', 'actor_id', 'note', 'origin',
        'simulation_run_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'delta_on_hand' => 'integer',
            'delta_reserved' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
