<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// M3a ORD-01..14, blueprint §6.2. Statuses (PRD §9.2):
// menunggu_pembayaran, dibayar, diproses, selesai, dibatalkan, kedaluwarsa.
// paid_at is set by OrderStateMachine when the order becomes dibayar
// (EVT-07: the order stores when it was created and when it was paid).
// picked_up_at is set by OrderStateMachine when the order becomes selesai
// (M5, keputusan 2026-11-08: the pickup-and-pay marker — the auditable
// moment the buyer picked the order up). Display label: diproses reads
// "Siap Diambil" (the order is ready for pickup), not "Diproses".
// Status changes never go through update() directly: they go through
// OrderStateMachine, which also writes order_status_histories (ORD-14).
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUSES = [
        'menunggu_pembayaran', 'dibayar', 'diproses',
        'selesai', 'dibatalkan', 'kedaluwarsa',
    ];

    public const STATUS_LABELS = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'dibayar' => 'Dibayar',
        'diproses' => 'Siap Diambil',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
        'kedaluwarsa' => 'Kedaluwarsa',
    ];

    protected $fillable = [
        'order_number', 'user_id', 'status', 'total_idr',
        'payment_deadline_at', 'paid_at', 'picked_up_at', 'checkout_key',
        'origin', 'simulation_run_id',
    ];

    protected function casts(): array
    {
        return [
            'total_idr' => 'integer',
            'payment_deadline_at' => 'datetime',
            'paid_at' => 'datetime',
            'picked_up_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function pendingPayment(): ?Payment
    {
        return $this->payments->firstWhere('status', 'pending')
            ?? $this->payments()->where('status', 'pending')->first();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function formattedTotal(): string
    {
        return 'Rp '.number_format((int) $this->total_idr, 0, ',', '.');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['dibatalkan', 'kedaluwarsa', 'selesai'], true);
    }
}
