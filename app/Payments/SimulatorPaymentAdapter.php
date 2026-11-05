<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\InternalDispatcher;
use App\Services\PaymentCallbackHandler;
use App\Services\PaymentStateMachine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// M3b PAY-55..57, blueprint §8.1/§8.4/§8.6. The ONLY payment adapter:
// it never calls an external API. createPayment builds the instruction
// through the app's own internal simulator gateway (POST
// /api/v1/simulator/payments via InternalDispatcher), so the
// create_payment request/response really happens and is recorded as a
// trace (M3c shows it). handleCallback verifies the HMAC signature
// over the raw body, validates the payload (PAY-26), applies the
// PAY-27 idempotency rules, and only then hands the callback to
// PaymentCallbackHandler. This class never touches stock or events
// and never changes a status directly: status changes always go
// through PaymentStateMachine.
class SimulatorPaymentAdapter implements PaymentAdapter
{
    public function __construct(
        private readonly InternalDispatcher $dispatcher,
        private readonly PaymentCallbackHandler $handler,
        private readonly PaymentStateMachine $stateMachine,
    ) {}

    // Blueprint §8.4: the instruction comes from the internal gateway,
    // which looks the payment up by `reference` (its ULID) and returns
    // the QR URL, VA number, token and expiry. A non-201 response is
    // an internal failure: the caller (checkout) rolls back.
    public function createPayment(Order $order, string $method, Payment $payment): PaymentInstruction
    {
        $response = $this->dispatcher->dispatchSimulatorPayment([
            'order_id' => $order->order_number,
            'amount' => (int) $order->total_idr,
            'currency' => 'IDR',
            'payment_method' => $method,
            'reference' => $payment->id,
            'callback_url' => url('/api/v1/webhooks/payments/simulator'),
            'return_url' => url('/pesanan/'.$order->id),
        ]);

        if ($response->getStatusCode() !== 201) {
            Log::error('SimulatorPaymentAdapter: gateway internal mengembalikan HTTP '.$response->getStatusCode().' untuk pembayaran '.$payment->id.'.');

            throw new \RuntimeException('Gateway simulasi internal gagal membuat instruksi pembayaran (HTTP '.$response->getStatusCode().').');
        }

        $data = json_decode((string) $response->getContent(), true);

        if (! is_array($data) || ! isset($data['qr_url'], $data['reference_code'], $data['payment_token'], $data['expires_at'])) {
            Log::error('SimulatorPaymentAdapter: respons gateway internal tidak lengkap untuk pembayaran '.$payment->id.'.');

            throw new \RuntimeException('Gateway simulasi internal mengembalikan instruksi yang tidak lengkap.');
        }

        return new PaymentInstruction(
            paymentId: (string) $data['payment_id'],
            referenceCode: (string) $data['reference_code'],
            qr: new QrPayload('url', (string) $data['qr_url']),
            vaNumber: isset($data['va_number']) && is_string($data['va_number']) ? $data['va_number'] : null,
            paymentToken: (string) $data['payment_token'],
            expiresAt: new \DateTimeImmutable((string) $data['expires_at']),
        );
    }

    public function getPaymentStatus(string $paymentId): PaymentStatus
    {
        $payment = Payment::find($paymentId);

        if ($payment === null) {
            throw new ModelNotFoundException('Pembayaran tidak ditemukan: '.$paymentId);
        }

        return new PaymentStatus(
            $payment->status,
            $payment->paid_at?->toImmutable(),
            $payment->reference_code,
        );
    }

    public function handleCallback(array $payload, ?string $signature, string $rawBody): CallbackResult
    {
        $requestId = (string) Str::uuid();
        $secret = (string) config('payment.webhook_secret', '');

        // PAY-25: signature first, before any payload handling. A
        // missing secret never verifies (fail closed).
        if (! SignatureVerifier::verify($rawBody, $signature, $secret)) {
            Log::warning('Webhook simulator: signature tidak valid.', [
                'payment_id' => $payload['payment_id'] ?? null,
                'request_id' => $requestId,
            ]);

            return new CallbackResult('unauthorized', null, null, 'Signature tidak valid.', $requestId);
        }

        // PAY-26: payload shape.
        $status = $payload['status'] ?? null;
        if (! in_array($status, ['paid', 'failed', 'expired'], true)
            || empty($payload['payment_id']) || ! is_string($payload['payment_id'])
            || empty($payload['order_id']) || ! is_string($payload['order_id'])
            || empty($payload['reference']) || ! is_string($payload['reference'])
        ) {
            return new CallbackResult('invalid', null, null, 'Payload tidak lengkap atau status tidak dikenal.', $requestId);
        }

        if ($status === 'paid') {
            $paidAt = $payload['paid_at'] ?? null;
            if (! is_string($paidAt) || strtotime($paidAt) === false) {
                return new CallbackResult('invalid', null, null, 'paid_at wajib dan harus tanggal valid bila status paid.', $requestId);
            }
        }

        $payment = Payment::find($payload['payment_id']);

        if ($payment === null) {
            return new CallbackResult('not_found', null, null, 'Pembayaran tidak ditemukan.', $requestId);
        }

        $payment->loadMissing('order');

        if ($payload['order_id'] !== $payment->order->order_number
            || $payload['reference'] !== $payment->reference_code
        ) {
            return new CallbackResult('invalid', $payment, $payment->order->status, 'order_id atau reference tidak cocok dengan pembayaran.', $requestId);
        }

        // PAY-23: the amount never comes from the payload.
        if (isset($payload['amount']) && (int) $payload['amount'] !== (int) $payment->amount_idr) {
            return new CallbackResult('invalid', $payment, $payment->order->status, 'Nominal tidak boleh berasal dari payload callback.', $requestId);
        }

        // PAY-27: idempotency on final payments.
        if ($payment->isFinal()) {
            if ($payment->status === $status) {
                return new CallbackResult('already_processed', $payment->fresh(), $payment->order->status, 'Callback sudah diproses sebelumnya.', $requestId);
            }

            return new CallbackResult('conflict', $payment->fresh(), $payment->order->status, 'Pembayaran sudah final dengan status berbeda.', $requestId);
        }

        return $this->handler->handle($payment, $status, $requestId);
    }

    // Payment row only: pending -> cancelled. The full buyer-cancel
    // cascade (order, stock, events) stays in CheckoutService.
    public function cancelPayment(string $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);

        $this->stateMachine->transition($payment, 'cancelled', 'pembeli_batal');
    }
}
