<?php

namespace App\Models;

use Database\Factories\PaymentStatusTransitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M3a PAY-28/PAY-39/PAY-43, blueprint §6.2. Append-only: no updated_at
// column and no update/delete code path anywhere. from_status is null
// for the initial row (null -> pending at creation).
class PaymentStatusTransition extends Model
{
    /** @use HasFactory<PaymentStatusTransitionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'from_status', 'to_status', 'trigger',
        'request_id', 'occurred_at', 'origin', 'simulation_run_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
