<?php

namespace App\Simulation;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SimulationRun;
use App\Models\User;
use App\Payments\QrPayload;
use App\Services\ActivityRecorder;
use App\Services\OrderStateMachine;
use App\Services\PaymentStateMachine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

// M4 simulator (SIM-30, blueprint §9.7). Applies ONE planned order's
// payment: create the pending Payment row, then drive it to its final
// status through the SAME state machines as the real flow, with
// backdated $at stamps and trigger 'panel_developer' — but WITHOUT an
// adapter, WITHOUT an HTTP trace, and WITHOUT touching stock (SIM-15).
//
// Never calls StockService, PaymentService::createForOrder, or the
// markPaid/markExpired/markCancelled cascades: those reject expired
// payments, touch stock, or hardcode their own triggers, and are
// unusable for backdated simulation (rencana §5 R2).
final class SimulationPaymentService
{
    /** Alphabet for SIMQR- references (no 0/O, 1/I/L) — PaymentService. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        private PaymentStateMachine $payments,
        private OrderStateMachine $orders,
        private ActivityRecorder $events,
    ) {}

    /**
     * $planned is one FunnelPlanner order: user_id, items, total_idr,
     * payment_method (code), final_status (selesai|dibatalkan|
     * kedaluwarsa), payment_status (paid|cancelled|expired),
     * booking_at, paid_at, cancelled_at, payment_deadline_at — all
     * instants CarbonImmutable (UTC). The order row already exists
     * (created by the executor); this method owns its initial history
     * entry, its payment, its transitions, and its purchase/cancel
     * events. Caller owns the surrounding transaction.
     */
    public function applyForOrder(Order $order, array $planned, SimulationRun $run): Payment
    {
        $user = User::query()->findOrFail((int) $order->user_id);
        $bookingAt = $this->toCarbon($planned['booking_at']);
        $devId = (int) $run->developer_id;
        $trigger = 'panel_developer';

        $this->orders->recordInitial($order, $trigger, 'developer', $devId, $bookingAt);

        $payment = $this->createPendingPayment($order, $planned, $run, $bookingAt);

        $this->payments->recordInitial($payment, $trigger, $bookingAt);

        $items = OrderItem::query()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        $final = (string) $planned['final_status'];

        if ($final === 'selesai') {
            $paidAt = $this->toCarbon($planned['paid_at']);

            $this->payments->transition($payment, 'paid', $trigger, null, $paidAt);
            $this->orders->transition($order, 'dibayar', $trigger, 'developer', $devId, null, $paidAt);
            $this->orders->transition($order, 'diproses', $trigger, 'developer', $devId, null, $paidAt);
            $this->orders->transition($order, 'selesai', $trigger, 'developer', $devId, null, $paidAt);

            foreach ($items as $item) {
                $this->events->recordPurchase($item, $user, 'developer_simulation', $run->id, $paidAt);
            }
        } elseif ($final === 'dibatalkan') {
            $cancelledAt = $this->toCarbon($planned['cancelled_at']);

            $this->payments->transition($payment, 'cancelled', $trigger, null, $cancelledAt);
            $this->orders->transition($order, 'dibatalkan', $trigger, 'developer', $devId, 'buyer', $cancelledAt);

            foreach ($items as $item) {
                $this->events->recordCancel($item, $user, 'buyer', 'developer_simulation', $run->id, $cancelledAt);
            }
        } else { // kedaluwarsa
            $cancelledAt = $this->toCarbon($planned['cancelled_at']);

            $this->payments->transition($payment, 'expired', $trigger, null, $cancelledAt);
            $this->orders->transition($order, 'kedaluwarsa', $trigger, 'developer', $devId, 'expired', $cancelledAt);

            foreach ($items as $item) {
                $this->events->recordCancel($item, $user, 'expired', 'developer_simulation', $run->id, $cancelledAt);
            }
        }

        return $payment->fresh();
    }

    private function createPendingPayment(Order $order, array $planned, SimulationRun $run, Carbon $bookingAt): Payment
    {
        $method = (string) $planned['payment_method'];
        $methodConfig = config('payment.methods.'.$method);

        if (! is_array($methodConfig) || ($methodConfig['active'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'payment_method' => "Metode pembayaran '{$method}' tidak aktif atau tidak dikenal.",
            ]);
        }

        $token = $this->uniqueToken();

        $payment = new Payment([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'provider' => 'simulator',
            'provider_payment_id' => null,
            'payment_method' => $method,
            'amount_idr' => (int) $planned['total_idr'],
            'currency' => 'IDR',
            'status' => 'pending',
            'reference_code' => $this->uniqueReference($methodConfig),
            'payment_token' => $token,
            // Deviation from the normal flow (documented): the normal
            // flow leaves qr_url null for VA methods and fills it from
            // the adapter only for QR methods. Here there is no adapter,
            // so qr_url is always the sim page URL — the instruction
            // view keys off the method kind, so the display is
            // unaffected, and every sim payment stays traceable to its
            // sim page. (PaymentService::createForOrder is not called.)
            'qr_url' => QrPayload::simPageUrl($token),
            'expires_at' => $this->toCarbon($planned['payment_deadline_at']),
            'paid_at' => null,
            'is_simulated' => true,
            'origin' => 'developer_simulation',
            'simulation_run_id' => $run->id,
        ]);

        // created_at/updated_at must be the booking instant, not now():
        // Eloquent's performInsert overwrites them, so timestamps are
        // disabled for this one save (the later transitions set
        // updated_at to their own stamps).
        $payment->timestamps = false;
        $payment->created_at = $bookingAt;
        $payment->updated_at = $bookingAt;
        $payment->save();
        $payment->timestamps = true;

        return $payment;
    }

    // --- Reference + token helpers, replicated from PaymentService ---
    // They are private there; extracting a shared helper would mean
    // editing PaymentService, which step 5 forbids. Kept identical so
    // a sim payment is indistinguishable in format from a real one.

    private function uniqueReference(array $methodConfig): string
    {
        do {
            $reference = $methodConfig['kind'] === 'va'
                ? $this->vaReference($methodConfig['bank_code'] ?? '')
                : 'SIMQR-'.$this->randomCode(10);
        } while (Payment::query()->where('reference_code', $reference)->exists());

        return $reference;
    }

    /** VA: '999' + 2-digit bank code + 11 random digits = 16 digits. */
    private function vaReference(string $bankCode): string
    {
        $digits = '';

        for ($i = 0; $i < 11; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return '999'.$bankCode.$digits;
    }

    private function randomCode(int $length): string
    {
        $alphabet = self::REFERENCE_ALPHABET;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, mb_strlen($alphabet) - 1)];
        }

        return $code;
    }

    private function uniqueToken(): string
    {
        do {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } while (Payment::query()->where('payment_token', $token)->exists());

        return $token;
    }

    /** Plan instants are CarbonImmutable (UTC); the domain services
     * take mutable Illuminate\Support\Carbon. */
    private function toCarbon(CarbonImmutable $instant): Carbon
    {
        return Carbon::instance($instant);
    }
}
