<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;

// M3b PAY-55, blueprint §8.1. The ONLY contract checkout, orders and
// analytics depend on. The driver is chosen by PAYMENT_DRIVER through
// the container (AppServiceProvider); no checkout/order/analytics code
// may refer to a concrete adapter class directly. Status changes never
// happen inside an adapter: they go through PaymentStateMachine, and
// stock/events are the PaymentCallbackHandler's job, so a future real
// provider adapter never needs to know the stock/analytics rules.
interface PaymentAdapter
{
    // Build the pay instruction (QR/VA/token) and expiry for one payment.
    public function createPayment(Order $order, string $method, Payment $payment): PaymentInstruction;

    // Latest status from the provider side (simulator: our own database).
    public function getPaymentStatus(string $paymentId): PaymentStatus;

    // Validate and apply a callback; returns a structured result
    // (processed / already_processed / rejected). Signature is verified
    // here, against the raw body, before anything else happens.
    public function handleCallback(array $payload, ?string $signature, string $rawBody): CallbackResult;

    // Cancel a still-pending payment (payment row only; the full
    // buyer-cancel cascade lives in CheckoutService::cancelOrder).
    public function cancelPayment(string $paymentId): void;
}
