<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// M3a PAY-01/PAY-02/PAY-03/PAY-05/PAY-10. Creates the single pending
// payment for an order. No QR/VA rendering, no instruction page, no
// webhook here: those are M3b. The payment_token is 32 CSPRNG bytes,
// URL-safe base64 (43 chars), unique, bound to this one payment.
// reference_code: QR methods get SIMQR- + 10 chars (PAY-10); VA
// methods get a 16-digit dummy number 999 + bank code + 11 random
// digits (PAY-08). qr_url stays NULL until M3b.
class PaymentService
{
    public function __construct(private readonly PaymentStateMachine $stateMachine) {}

    // PAY-01: the active method catalog, from config/payment.php.
    public function methods(): array
    {
        $methods = config('payment.methods', []);

        return array_values(array_filter(
            $methods,
            fn (array $method) => ($method['active'] ?? false) === true,
        ));
    }

    public function method(string $code): ?array
    {
        $methods = config('payment.methods', []);

        return $methods[$code] ?? null;
    }

    public function isActiveMethod(string $code): bool
    {
        $method = $this->method($code);

        return $method !== null && ($method['active'] ?? false) === true;
    }

    // PAY-03: one pending payment for a menunggu_pembayaran order.
    // Amount is the server-computed order total; expires_at equals the
    // order's payment_deadline_at (ORD-05 / PAY-04). The caller
    // (CheckoutService) owns the surrounding transaction.
    public function createForOrder(Order $order, string $methodCode): Payment
    {
        $method = $this->method($methodCode);

        if ($method === null || ($method['active'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran tidak dikenal atau tidak aktif.',
            ]);
        }

        if ($order->status !== 'menunggu_pembayaran') {
            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran hanya dapat dibuat untuk pesanan yang menunggu pembayaran.',
            ]);
        }

        if (now()->greaterThanOrEqualTo($order->payment_deadline_at)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Batas waktu bayar pesanan sudah terlewati.',
            ]);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'provider' => 'simulator',
            'provider_payment_id' => null,
            'payment_method' => $methodCode,
            'amount_idr' => $order->total_idr,
            'currency' => 'IDR',
            'status' => 'pending',
            'reference_code' => $this->uniqueReference($method),
            'payment_token' => $this->uniqueToken(),
            'qr_url' => null,
            'expires_at' => $order->payment_deadline_at,
            'paid_at' => null,
            'is_simulated' => true,
            'origin' => $order->origin,
            'simulation_run_id' => $order->simulation_run_id,
        ]);

        $this->stateMachine->recordInitial($payment, 'checkout');

        return $payment;
    }

    // PAY-05: >= 32 bytes of CSPRNG entropy, URL-safe, unique.
    private function uniqueToken(): string
    {
        do {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } while (Payment::query()->where('payment_token', $token)->exists());

        return $token;
    }

    private function uniqueReference(array $method): string
    {
        do {
            $reference = ($method['kind'] ?? 'qr') === 'va'
                ? $this->vaReference((string) ($method['bank_code'] ?? '00'))
                : 'SIMQR-'.$this->randomCode(10);
        } while (Payment::query()->where('reference_code', $reference)->exists());

        return $reference;
    }

    // PAY-08: 999 + bank code + 11 random digits = 16 digits.
    private function vaReference(string $bankCode): string
    {
        $digits = '';
        for ($i = 0; $i < 11; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return '999'.$bankCode.$digits;
    }

    // Unambiguous alphabet (no 0/O, 1/I/L) — same alphabet as order
    // numbers (ORD-03), so neither can be misread.
    private function randomCode(int $length): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
