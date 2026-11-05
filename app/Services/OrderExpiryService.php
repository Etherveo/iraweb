<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// M3a/M3b PAY-32/PAY-34/PAY-35/PAY-36. Two expiry paths, one service:
//
// - On access (PAY-35): buyer/admin/instruction/sim pages call
//   expireIfOverdue()/expireOverdueForUser() before rendering, so the
//   status a user sees is never stale. This is the DIRECT path: it
//   calls PaymentStateMachine straight away, so it works even when
//   the webhook secret is not configured.
// - Scheduled job (PAY-34/PAY-36): the artisan command
//   `ira:expire-overdue-orders` (wired to the scheduler in M3b) calls
//   expireOverdueViaWebhook(): an overdue order WITH a pending
//   payment expires through a SIGNED internal webhook callback
//   (status=expired), so the event is a real recorded HTTP event;
//   an order WITHOUT a pending payment (e.g. after a failed payment)
//   expires directly, exactly like the on-access path.
//
// Expiry is idempotent: an order that is no longer
// menunggu_pembayaran (or not yet past its deadline) is a no-op, and
// a second expiry call finds the order already kedaluwarsa and does
// nothing.
class OrderExpiryService
{
    public function __construct(
        private readonly OrderStateMachine $orders,
        private readonly PaymentStateMachine $payments,
        private readonly StockService $stock,
        private readonly ActivityRecorder $events,
        private readonly InternalDispatcher $dispatcher,
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

    // M3b PAY-34/PAY-36: the scheduled-job path. Overdue orders are
    // expired through the signed internal webhook (one callback per
    // order with a pending payment), so the expired event is a real
    // recorded HTTP event; orders without a pending payment expire
    // directly. A non-200 webhook response leaves the order alone
    // (it is retried on the next run) and is logged. Idempotent:
    // already-expired orders are never selected again.
    public function expireOverdueViaWebhook(int $limit = 200): int
    {
        $orders = Order::query()
            ->where('status', 'menunggu_pembayaran')
            ->where('payment_deadline_at', '<=', now())
            ->orderBy('payment_deadline_at')
            ->limit($limit)
            ->get();

        $expired = 0;

        foreach ($orders as $order) {
            $payment = $order->payments()->where('status', 'pending')->first();

            if ($payment !== null) {
                $response = $this->dispatcher->dispatchWebhook('simulator', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->order_number,
                    'status' => 'expired',
                    'reference' => $payment->reference_code,
                ]);

                if ($response->getStatusCode() === 200) {
                    $expired++;
                } else {
                    Log::warning('Kedaluwarsa: webhook internal mengembalikan HTTP '.$response->getStatusCode().' untuk pembayaran '.$payment->id.'; pesanan tetap menunggu dan akan dicoba lagi.');
                }
            } elseif ($this->expireIfOverdue($order)) {
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
