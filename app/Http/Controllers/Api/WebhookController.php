<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Payments\PaymentAdapter;
use App\Payments\ProviderRegistry;
use App\Services\TraceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// M3b PAY-24..27, blueprint §8.6. The versioned payment webhook.
// Provider is checked against ProviderRegistry (unknown → 404); the
// signature is verified by the adapter over the raw body (PAY-25);
// the payload is validated by the adapter (PAY-26); idempotency is
// PAY-27 (same result for a final payment → 200 already_processed,
// different result → 409). The real request/response is recorded as a
// trace (kind callback, or expired_callback for status=expired) so
// the M3c panel shows what really happened. This controller never
// changes a status itself: the adapter + PaymentCallbackHandler do.
class WebhookController extends Controller
{
    public function __construct(
        private readonly TraceRecorder $traces,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        if (! ProviderRegistry::isKnownProvider($provider)) {
            return response()->json(['result' => 'error', 'message' => 'Provider tidak dikenal.'], 404);
        }

        $rawBody = (string) $request->getContent();
        $signature = $request->header('X-Simulator-Signature');
        $requestId = (string) Str::uuid();

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            $body = ['result' => 'invalid', 'message' => 'Body harus JSON yang valid.'];
            $this->traces->record('callback', null, null, 'POST', $request->getPathInfo(), $request->headers->all(), $rawBody, 422, $body, $requestId);

            return response()->json($body, 422);
        }

        $adapter = app(PaymentAdapter::class);
        $result = $adapter->handleCallback($payload, $signature, $rawBody);

        $kind = ($payload['status'] ?? null) === 'expired' ? 'expired_callback' : 'callback';
        $responseBody = $result->toArray();

        $this->traces->record(
            $kind,
            $result->payment,
            $result->payment?->order_id,
            'POST',
            $request->getPathInfo(),
            $request->headers->all(),
            $payload,
            $result->httpStatus(),
            $responseBody,
            $result->requestId ?? $requestId,
        );

        return response()->json($responseBody, $result->httpStatus());
    }
}
