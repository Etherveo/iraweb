<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\TraceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// M3b, blueprint §8.4. The simulator adapter's internal gateway.
// Reachable ONLY through InternalDispatcher (middleware
// InternalDispatchOnly); a real external HTTP request → 404. It
// validates the instruction request against the stored payment
// (order number, amount, method must match) and returns the pay
// instruction: reference, QR URL, VA number (VA methods), token and
// expiry. The request/response is recorded as a create_payment trace
// (M3c shows it). No secret is ever accepted or returned here.
class SimulatorGatewayController extends Controller
{
    public function __construct(
        private readonly TraceRecorder $traces,
    ) {}

    public function create(Request $request): JsonResponse
    {
        $requestId = (string) Str::uuid();
        $data = $request->json()->all();

        if (! is_array($data)) {
            $data = [];
        }

        $payment = null;

        if (is_string($data['reference'] ?? null)) {
            $payment = Payment::find($data['reference']);
        }

        if ($payment === null) {
            $body = ['result' => 'error', 'message' => 'Pembayaran tidak ditemukan.'];
            $this->traces->record('create_payment', null, null, 'POST', $request->getPathInfo(), $request->headers->all(), $data, 404, $body, $requestId);

            return response()->json($body, 404);
        }

        $payment->loadMissing('order');

        if ((int) ($data['amount'] ?? 0) !== (int) $payment->amount_idr
            || ($data['order_id'] ?? null) !== $payment->order->order_number
            || ($data['payment_method'] ?? null) !== $payment->payment_method
        ) {
            $body = ['result' => 'error', 'message' => 'Data instruksi tidak cocok dengan pembayaran.'];
            $this->traces->record('create_payment', $payment, $payment->order_id, 'POST', $request->getPathInfo(), $request->headers->all(), $data, 422, $body, $requestId);

            return response()->json($body, 422);
        }

        $kind = config('payment.methods.'.$payment->payment_method.'.kind', 'qr');

        $body = [
            'payment_id' => $payment->id,
            'reference_code' => $payment->reference_code,
            'qr_url' => url('/pay/sim/'.$payment->payment_token),
            'va_number' => $kind === 'va' ? $payment->reference_code : null,
            'payment_token' => $payment->payment_token,
            'expires_at' => $payment->expires_at->toIso8601String(),
        ];

        $this->traces->record('create_payment', $payment, $payment->order_id, 'POST', $request->getPathInfo(), $request->headers->all(), $data, 201, $body, $requestId);

        return response()->json($body, 201);
    }
}
