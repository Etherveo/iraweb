<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Validation\ValidationException;

// M3a ORD-06/ORD-14, PRD §9.2. The ONLY writer of order status changes
// (and of order_status_histories). Any other transition is rejected
// with no data change. The caller owns the surrounding transaction;
// this class never opens one of its own.
class OrderStateMachine
{
    // Allowed transitions from each status (PRD §9.2). Final statuses
    // (dibatalkan, kedaluwarsa, selesai) have no outgoing transition.
    public const TRANSITIONS = [
        'menunggu_pembayaran' => ['dibayar', 'dibatalkan', 'kedaluwarsa'],
        'dibayar' => ['diproses'],
        'diproses' => ['selesai'],
        'dibatalkan' => [],
        'kedaluwarsa' => [],
        'selesai' => [],
    ];

    public const TRIGGERS = [
        'pembeli', 'callback_simulasi', 'job_kedaluwarsa', 'admin', 'panel_developer',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(
        Order $order,
        string $to,
        string $trigger,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $reason = null,
    ): Order {
        $from = $order->status;

        if (! self::canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => 'Status pesanan tidak dapat diubah dari '.$order->statusLabel().' ke '.(Order::STATUS_LABELS[$to] ?? $to).'.',
            ]);
        }

        $order->update([
            'status' => $to,
            // EVT-07: the order stores when it was paid.
            'paid_at' => $to === 'dibayar' ? now() : $order->paid_at,
            'updated_at' => now(),
        ]);

        $this->writeHistory($order, $from, $to, $trigger, $actorType, $actorId, $reason);

        return $order->fresh();
    }

    // The initial history row for a new order (null -> menunggu_pembayaran).
    public function recordInitial(Order $order, string $trigger, ?string $actorType, ?int $actorId): void
    {
        $this->writeHistory($order, null, 'menunggu_pembayaran', $trigger, $actorType, $actorId, null);
    }

    private function writeHistory(
        Order $order,
        ?string $from,
        string $to,
        string $trigger,
        ?string $actorType,
        ?int $actorId,
        ?string $reason,
    ): void {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'trigger' => $trigger,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'occurred_at' => now(),
            'origin' => $order->origin,
            'simulation_run_id' => $order->simulation_run_id,
            'created_at' => now(),
        ]);
    }
}
