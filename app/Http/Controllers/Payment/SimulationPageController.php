<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\InternalDispatcher;
use App\Services\OrderExpiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

// M3b PAY-15..23. The public simulator page, opened by scanning the
// QR (or the link) — the payment token is a limited capability, so
// no login is needed (PAY-22/K-12). Only the data this one payment
// needs is shown: order number, items, total, method, reference/VA,
// created + expiry. NEVER the buyer's name, email, or any personal
// data. Pay/Fail go through the signed internal webhook (PAY-36),
// never straight to the callback handler.
class SimulationPageController extends Controller
{
    public function __construct(
        private readonly OrderExpiryService $expiry,
        private readonly InternalDispatcher $dispatcher,
    ) {}

    public function show(string $token): View
    {
        $payment = $this->findByToken($token);
        $this->expiry->expireIfOverdue($payment->order);
        $payment = $payment->fresh(['order.items']);

        return $this->render($payment, null);
    }

    // PAY-16: Bayar Sekarang (Simulasi).
    public function pay(Request $request, string $token): View
    {
        return $this->act($token, 'paid');
    }

    // PAY-17: Simulasikan Gagal.
    public function fail(Request $request, string $token): View
    {
        return $this->act($token, 'failed');
    }

    private function act(string $token, string $status): View
    {
        $payment = $this->findByToken($token);
        $this->expiry->expireIfOverdue($payment->order);
        $payment = $payment->fresh(['order.items']);

        $message = null;

        if ($payment->status !== 'pending') {
            // PAY-21: a final payment rejects every action attempt.
            Log::warning('Halaman simulasi: aksi ditolak, pembayaran sudah final.', [
                'payment_id' => $payment->id, 'status' => $payment->status,
            ]);
            $message = 'Pembayaran sudah berstatus '.$payment->statusLabel().' dan tidak dapat diubah.';
        } elseif (now()->greaterThanOrEqualTo($payment->expires_at)) {
            // Expiry was just processed above; the page renders the
            // expired state (PAY-20).
            $message = 'Pembayaran sudah melewati batas waktu bayar.';
        } else {
            // PAY-36: the action is a signed internal sub-request to
            // the same webhook endpoint a real provider would call.
            $payload = [
                'payment_id' => $payment->id,
                'order_id' => $payment->order->order_number,
                'status' => $status,
                'reference' => $payment->reference_code,
            ];

            if ($status === 'paid') {
                $payload['paid_at'] = now()->toIso8601String();
            }

            $response = $this->dispatcher->dispatchWebhook('simulator', $payload);

            if ($response->getStatusCode() === 200) {
                $message = $status === 'paid'
                    ? 'Pembayaran simulasi berhasil. Pesanan sudah dibayar.'
                    : 'Pembayaran simulasi digagalkan. Pesanan masih menunggu pembayaran.';
            } else {
                Log::warning('Halaman simulasi: webhook internal mengembalikan HTTP '.$response->getStatusCode().'.', [
                    'payment_id' => $payment->id, 'status' => $status,
                ]);
                $message = 'Simulasi tidak dapat diproses saat ini (HTTP '.$response->getStatusCode().'). Coba lagi.';
            }
        }

        $payment = $payment->fresh(['order.items']);

        return $this->render($payment, $message);
    }

    private function render(Payment $payment, ?string $message): View
    {
        $order = $payment->order;
        $method = config('payment.methods.'.$payment->payment_method, []);

        return view('payments.sim-show', [
            'payment' => $payment,
            'order' => $order,
            'method' => $method,
            'isVa' => ($method['kind'] ?? 'qr') === 'va',
            'message' => $message,
            'expiresAtIso' => $payment->expires_at->toIso8601String(),
            'serverNowIso' => now()->toIso8601String(),
        ]);
    }

    // PAY-19: an unknown or malformed token → the same generic 404,
    // without revealing whether the token ever existed. Attempts are
    // written to the security log (Log::warning), never to the UI.
    private function findByToken(string $token): Payment
    {
        if (! preg_match('/^[A-Za-z0-9\-_]{43}$/', $token)) {
            Log::warning('Halaman simulasi: token format tidak dikenal.');

            abort(404);
        }

        $payment = Payment::query()->where('payment_token', $token)->first();

        if ($payment === null) {
            Log::warning('Halaman simulasi: token tidak dikenal.');

            abort(404);
        }

        return $payment;
    }
}
