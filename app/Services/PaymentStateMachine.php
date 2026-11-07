<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentStatusTransition;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// M3a/M3b PAY-28/PAY-29/PAY-30/PAY-32/PAY-33, PRD §9.1/§9.2. The ONLY
// writer of payment status changes (and of payment_status_transitions).
// Final statuses (paid, failed, expired, cancelled) can never change
// again. markPaid/markExpired/markCancelled are the full cascades:
// payment + order + stock + events in ONE transaction, so a failure
// anywhere rolls everything back; markFailed changes only the payment
// (PAY-30). In M3b the webhook handler (PaymentCallbackHandler) is
// the only caller of these methods from the payment flow — a duplicate
// paid call is rejected (the payment is already final), so stock and
// purchase events can never double (PAY-27 maps that rejection to
// 200 already_processed).
//
// M4 SIM-29: transition()/recordInitial()/writeTransition() accept an
// optional ?Carbon $at. When null (the default) every stamp is now(),
// exactly as before; when given, the stamps use $at, so a backdated
// simulation run can place the transition inside its period. The
// mark* cascades are NOT extended and NOT used by the simulator
// (rencana §2.4/R2: they reject expired payments, touch stock, and
// hardcode their triggers); the simulator uses transition() directly.
class PaymentStateMachine
{
    public const TRANSITIONS = [
        'pending' => ['paid', 'failed', 'expired', 'cancelled'],
        'paid' => [],
        'failed' => [],
        'expired' => [],
        'cancelled' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function __construct(
        private readonly OrderStateMachine $orders,
        private readonly StockService $stock,
        private readonly ActivityRecorder $events,
    ) {}

    // Single status change with its transition row. Caller owns the
    // surrounding transaction when this is part of a larger flow.
    public function transition(
        Payment $payment,
        string $to,
        string $trigger,
        ?string $requestId = null,
        ?Carbon $at = null,
    ): Payment {
        $from = $payment->status;

        if (! self::canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => 'Status pembayaran tidak dapat diubah dari '.(Payment::STATUS_LABELS[$from] ?? $from).' ke '.(Payment::STATUS_LABELS[$to] ?? $to).'.',
            ]);
        }

        $stamp = $at ?? now();

        $payment->update([
            'status' => $to,
            'paid_at' => $to === 'paid' ? $stamp : $payment->paid_at,
            'updated_at' => $stamp,
        ]);

        $this->writeTransition($payment, $from, $to, $trigger, $requestId, $at);

        return $payment->fresh();
    }

    // The initial transition row for a new payment (null -> pending).
    public function recordInitial(Payment $payment, string $trigger, ?Carbon $at = null): void
    {
        $this->writeTransition($payment, null, 'pending', $trigger, null, $at);
    }

    // PAY-29 / STK-03 / EVT-05: pending -> paid, order -> dibayar,
    // stock confirmed (reserved -= qty, on-hand -= qty), one purchase
    // event per item. All in one transaction.
    public function markPaid(Payment $payment, string $trigger = 'callback_simulasi', ?string $requestId = null): Payment
    {
        return DB::transaction(function () use ($payment, $trigger, $requestId) {
            $payment = $payment->fresh();
            $order = $payment->order()->with('items')->first();

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Pembayaran sudah berstatus '.(Payment::STATUS_LABELS[$payment->status] ?? $payment->status).' dan tidak dapat diubah.',
                ]);
            }

            if (now()->greaterThanOrEqualTo($payment->expires_at)) {
                throw ValidationException::withMessages([
                    'status' => 'Pembayaran sudah melewati batas waktu bayar.',
                ]);
            }

            if ($order->status !== 'menunggu_pembayaran') {
                throw ValidationException::withMessages([
                    'status' => 'Pesanan tidak lagi menunggu pembayaran.',
                ]);
            }

            $this->transition($payment, 'paid', $trigger, $requestId);
            $this->orders->transition($order, 'dibayar', $trigger, 'sistem', null);
            $this->stock->confirm($order, 'sistem', null);

            foreach ($order->items as $item) {
                $this->events->recordPurchase($item, $order->user);
            }

            return $payment->fresh();
        });
    }

    // M3b PAY-28/PAY-30: pending -> failed. ONLY the payment changes:
    // the order stays menunggu_pembayaran, no purchase event is
    // recorded, and the stock stays reserved (U-12) until the order
    // is cancelled or expires. The buyer may repay (PAY-31) or cancel.
    public function markFailed(Payment $payment, string $trigger = 'callback_simulasi', ?string $requestId = null): Payment
    {
        return DB::transaction(function () use ($payment, $trigger, $requestId) {
            $payment = $payment->fresh();

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Pembayaran sudah berstatus '.(Payment::STATUS_LABELS[$payment->status] ?? $payment->status).' dan tidak dapat diubah.',
                ]);
            }

            $this->transition($payment, 'failed', $trigger, $requestId);

            return $payment->fresh();
        });
    }

    // PAY-32 / STK-04 / EVT-06: pending -> expired, order ->
    // kedaluwarsa, stock released, one cancel event (reason expired)
    // per item. All in one transaction.
    public function markExpired(Payment $payment, string $trigger = 'job_kedaluwarsa', ?string $requestId = null): Payment
    {
        return DB::transaction(function () use ($payment, $trigger, $requestId) {
            $payment = $payment->fresh();
            $order = $payment->order()->with('items')->first();

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Pembayaran sudah berstatus '.(Payment::STATUS_LABELS[$payment->status] ?? $payment->status).' dan tidak dapat diubah.',
                ]);
            }

            if ($order->status !== 'menunggu_pembayaran') {
                throw ValidationException::withMessages([
                    'status' => 'Pesanan tidak lagi menunggu pembayaran.',
                ]);
            }

            $this->transition($payment, 'expired', $trigger, $requestId);
            $this->orders->transition($order, 'kedaluwarsa', $trigger, 'sistem', null, 'expired');
            $this->stock->release($order, 'sistem', null);

            foreach ($order->items as $item) {
                $this->events->recordCancel($item, $order->user, 'expired');
            }

            return $payment->fresh();
        });
    }

    // PAY-33 / STK-04 / EVT-06 (ORD-07): pending -> cancelled, order ->
    // dibatalkan, stock released, one cancel event (reason buyer) per
    // item. All in one transaction.
    public function markCancelled(Payment $payment, ?User $actor = null, string $trigger = 'pembeli_batal', ?string $requestId = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $trigger, $requestId) {
            $payment = $payment->fresh();
            $order = $payment->order()->with('items')->first();

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Pembayaran sudah berstatus '.(Payment::STATUS_LABELS[$payment->status] ?? $payment->status).' dan tidak dapat diubah.',
                ]);
            }

            if ($order->status !== 'menunggu_pembayaran') {
                throw ValidationException::withMessages([
                    'status' => 'Pesanan tidak lagi menunggu pembayaran.',
                ]);
            }

            $this->transition($payment, 'cancelled', $trigger, $requestId);
            $this->orders->transition($order, 'dibatalkan', 'pembeli', 'pembeli', $actor?->id, 'buyer');
            $this->stock->release($order, 'pembeli', $actor?->id);

            foreach ($order->items as $item) {
                $this->events->recordCancel($item, $order->user, 'buyer');
            }

            return $payment->fresh();
        });
    }

    private function writeTransition(
        Payment $payment,
        ?string $from,
        string $to,
        string $trigger,
        ?string $requestId,
        ?Carbon $at = null,
    ): void {
        $stamp = $at ?? now();

        PaymentStatusTransition::create([
            'payment_id' => $payment->id,
            'from_status' => $from,
            'to_status' => $to,
            'trigger' => $trigger,
            'request_id' => $requestId,
            'occurred_at' => $stamp,
            'origin' => $payment->origin,
            'simulation_run_id' => $payment->simulation_run_id,
            'created_at' => $stamp,
        ]);
    }
}
