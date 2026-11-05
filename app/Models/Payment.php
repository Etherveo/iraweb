<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// M3a PAY-03/PAY-28, blueprint §6.2. id is a ULID (payment_id).
// qr_url stays NULL in M3a (the instruction/simulator page is M3b).
// Status changes never go through update() directly: they go through
// PaymentStateMachine, which also writes payment_status_transitions.
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUSES = ['pending', 'paid', 'failed', 'expired', 'cancelled'];

    public const STATUS_LABELS = [
        'pending' => 'Menunggu Pembayaran',
        'paid' => 'Terbayar',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'order_id', 'user_id', 'provider', 'provider_payment_id',
        'payment_method', 'amount_idr', 'currency', 'status',
        'reference_code', 'payment_token', 'qr_url', 'expires_at',
        'paid_at', 'is_simulated', 'origin', 'simulation_run_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'is_simulated' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(PaymentStatusTransition::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function formattedAmount(): string
    {
        return 'Rp '.number_format((int) $this->amount_idr, 0, ',', '.');
    }

    public function isFinal(): bool
    {
        return $this->status !== 'pending';
    }
}
