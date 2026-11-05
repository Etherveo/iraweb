<?php

namespace App\Models;

use Database\Factories\PaymentHttpTraceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M3b PAY-37/PAY-44, blueprint §8.7. One row per real HTTP event of
// the payment flow (create_payment, callback, expired_callback).
// Append-only: rows are never updated. The PANEL that displays these
// rows is M3c; M3b only writes them. Headers and bodies are sanitized
// by TraceRecorder before they reach this table: no secret, signature
// value, cookie, or APP_KEY is ever stored here (PAY-44).
class PaymentHttpTrace extends Model
{
    /** @use HasFactory<PaymentHttpTraceFactory> */
    use HasFactory;

    public const KINDS = ['create_payment', 'callback', 'expired_callback'];

    protected $fillable = [
        'payment_id', 'order_id', 'request_id', 'kind', 'method', 'path',
        'request_headers_json', 'request_body_json', 'response_status',
        'response_body_json', 'occurred_at', 'origin', 'simulation_run_id',
    ];

    protected function casts(): array
    {
        return [
            'request_headers_json' => 'array',
            'request_body_json' => 'array',
            'response_body_json' => 'array',
            'response_status' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
