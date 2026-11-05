<?php

namespace App\Services;

use App\Models\Payment;
use App\Payments\CallbackResult;

// M3b PAY-27/PAY-29/PAY-30, blueprint §7.3. Applies a VALIDATED
// callback to a payment. Only reachable through the webhook (the
// adapter calls this after signature + payload validation), never
// directly from a sim page action or an expiry job — PAY-36.
//
// The paid-after-expiry rule (blueprint §7.3): a `paid` callback for
// a payment already past its deadline is processed as `expired`,
// never as paid. Every status change goes through
// PaymentStateMachine, so stock, events and history stay atomic with
// the payment change.
class PaymentCallbackHandler
{
    public function __construct(
        private readonly PaymentStateMachine $payments,
    ) {}

    public function handle(Payment $payment, string $status, ?string $requestId = null): CallbackResult
    {
        $payment = $payment->fresh();

        if ($payment->status !== 'pending') {
            // Defensive: the adapter already enforces idempotency, but
            // a direct call must never change a final payment.
            return new CallbackResult('conflict', $payment, $payment->order->status, 'Pembayaran sudah final.', $requestId);
        }

        if ($status === 'paid' && now()->greaterThanOrEqualTo($payment->expires_at)) {
            $this->payments->markExpired($payment, 'job_kedaluwarsa', $requestId);
            $payment = $payment->fresh();

            return new CallbackResult('processed', $payment, $payment->order->status, 'Pembayaran sudah melewati batas waktu; diproses sebagai kedaluwarsa.', $requestId);
        }

        match ($status) {
            'paid' => $this->payments->markPaid($payment, 'callback_simulasi', $requestId),
            // PAY-30: failed changes only the payment; the order stays
            // menunggu_pembayaran and the stock stays reserved (U-12).
            'failed' => $this->payments->markFailed($payment, 'callback_simulasi', $requestId),
            'expired' => $this->payments->markExpired($payment, 'job_kedaluwarsa', $requestId),
            default => throw new \InvalidArgumentException('Status callback tidak dikenal: '.$status),
        };

        $payment = $payment->fresh();

        return new CallbackResult('processed', $payment, $payment->order->status, 'Callback diproses.', $requestId);
    }
}
