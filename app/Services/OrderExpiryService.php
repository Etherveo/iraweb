<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// M3a PAY-32/PAY-34/PAY-35 (on-access variant). There is NO scheduled
// job in M3a (PAY-34's scheduler is M3b): expiry is processed when a
// human or the artisan command looks at an order. The command
// `ira:expire-overdue-orders` calls expireOverdue(); buyer/admin
// order pages call expireIfOverdue()/expireOverdueForUser() before
// rendering, so the status a user sees is never stale (PAY-35).
// Expiry is idempotent: an order that is no longer menunggu_pembayaran
// (or not yet past its deadline) is a no-op, and a second expiry call
// finds the order already kedaluwarsa and does nothing.
class OrderExpiryService
{
    public function __construct(
        private readonly OrderStateMachine $orders,
        private readonly PaymentStateMachine $payments,
        private readonly StockService $stock,
        private readonly ActivityRecorder $events,
    ) {}

    // Expire one order if it is overdue. Returns true when it expired.
    public function expireIfOverdue(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $order = $order->fresh(['items']);

            if ($order->status !== 'menunggu_pembayaran') {
                return false;
            }

            if (now()->lessThan($order->payment_deadline_at)) {
                return false;
            }

            return $this->expire($order);
        });
    }

    // Expire all overdue orders (artisan command / admin pages).
    public function expireOverdue(int $limit = 200): int
    {
        $orders = Order::query()
            ->where('status', 'menunggu_pembayaran')
            ->where('payment_deadline_at', '<=', now())
            ->orderBy('payment_deadline_at')
            ->limit($limit)
            ->get();

        $expired = 0;
        foreach ($orders as $order) {
            if ($this->expireIfOverdue($order)) {
                $expired++;
            }
        }

        return $expired;
    }

    // Same, scoped to one buyer (buyer pages only expire their own
    // orders on access; other buyers' orders expire on their own
    // access, an admin visit, or the artisan command).
    public function expireOverdueForUser(User $user, int $limit = 200): int
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'menunggu_pembayaran')
            ->where('payment_deadline_at', '<=', now())
            ->orderBy('payment_deadline_at')
            ->limit($limit)
            ->get();

        $expired = 0;
        foreach ($orders as $order) {
            if ($this->expireIfOverdue($order)) {
                $expired++;
            }
        }

        return $expired;
    }

    // The actual expiry, inside the caller's transaction. Assumes the
    // order is menunggu_pembayaran and past its deadline.
    private function expire(Order $order): bool
    {
        $payment = $order->payments()->where('status', 'pending')->first();

        if ($payment !== null) {
            // PaymentStateMachine does payment -> expired, order ->
            // kedaluwarsa, stock release, cancel events, in one tx.
            $this->payments->markExpired($payment, 'job_kedaluwarsa');
        } else {
            // No pending payment (e.g. an earlier payment failed):
            // the order still expires, the stock still releases.
            $this->orders->transition($order, 'kedaluwarsa', 'job_kedaluwarsa', 'sistem', null, 'expired');
            $this->stock->release($order, 'sistem', null);

            foreach ($order->items as $item) {
                $this->events->recordCancel($item, $order->user, 'expired');
            }
        }

        return true;
    }
}
