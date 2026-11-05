<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentHttpTrace;
use App\Payments\SignatureVerifier;
use Illuminate\Support\Collection;

// M3c PAY-37..42, blueprint §8.7. READ-ONLY presenter for the
// "Simulated HTTP Request & Response" panel: it only READS stored
// payment_http_traces / payment_status_transitions rows, never writes
// them (PAY-43). The cURL command (PAY-41) is built from the stored
// callback request: the body is the stored JSON re-encoded, and the
// signature is a fresh HMAC-SHA256 over EXACTLY those bytes
// (SignatureVerifier::sign), so running the command replays the same
// callback and follows PAY-25/PAY-27 (a final payment answers
// "already_processed" with no double effect). The signature is an
// HMAC output, not the secret — the secret itself is never displayed
// or stored (PAY-44/PAY-60).
class PaymentTracePanel
{
    /**
     * @return array{create: ?PaymentHttpTrace, callbacks: Collection<int, PaymentHttpTrace>, transitions: Collection<int, \App\Models\PaymentStatusTransition>, curl: ?string, curlUnavailableReason: ?string}
     */
    public function forPayment(Payment $payment): array
    {
        $traces = PaymentHttpTrace::query()
            ->where('payment_id', $payment->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $create = $traces->where('kind', 'create_payment')->last();
        $callbacks = $traces->whereIn('kind', ['callback', 'expired_callback'])
            ->sortByDesc('occurred_at')
            ->sortByDesc('id')
            ->values();

        $transitions = $payment->transitions()->orderBy('occurred_at')->orderBy('id')->get();

        $curl = $this->curlForCallback($callbacks->first());
        $curlReason = null;

        if ($callbacks->isNotEmpty() && $curl === null) {
            $curlReason = (string) config('payment.webhook_secret', '') === ''
                ? 'Perintah cURL tidak tersedia karena secret webhook belum dikonfigurasi di server.'
                : 'Perintah cURL tidak dapat dibuat dari jejak callback yang tersimpan.';
        }

        return [
            'create' => $create,
            'callbacks' => $callbacks,
            'transitions' => $transitions,
            'curl' => $curl,
            'curlUnavailableReason' => $curlReason,
        ];
    }

    // PAY-41: a runnable cURL for the stored callback request, or
    // null when it cannot be built (no callback trace, a non-array
    // stored body, or no webhook secret — a signature made with an
    // empty key never verifies, so showing one would be misleading).
    public function curlForCallback(?PaymentHttpTrace $trace): ?string
    {
        if ($trace === null) {
            return null;
        }

        $body = $trace->request_body_json;

        if (! is_array($body)) {
            return null;
        }

        $secret = (string) config('payment.webhook_secret', '');

        if ($secret === '') {
            return null;
        }

        $bodyString = (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $url = rtrim((string) config('app.url'), '/').$trace->path;
        $signature = SignatureVerifier::sign($bodyString, $secret);

        return "curl -X POST '{$url}' \\\n"
            ."  -H 'Content-Type: application/json' \\\n"
            ."  -H 'X-Simulator-Signature: {$signature}' \\\n"
            ."  -d '{$bodyString}'";
    }

    // PAY-38: the order status recorded in the callback response (the
    // webhook returns order_status in its body). Null when the stored
    // response carries none (e.g. a rejected attempt).
    public static function orderStatusAfter(PaymentHttpTrace $trace): ?string
    {
        $body = $trace->response_body_json;

        if (! is_array($body) || ! isset($body['order_status']) || ! is_string($body['order_status'])) {
            return null;
        }

        return $body['order_status'];
    }

    // JSON as shown in the panel: pretty-printed, slashes and
    // non-ASCII kept readable. Always rendered with Blade {{ }}
    // (escaped) — never with {!! !!} (PAY-42).
    public static function prettyJson(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) ($value ?? '');
        }

        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
