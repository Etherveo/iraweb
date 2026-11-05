<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentHttpTrace;
use Illuminate\Support\Str;

// M3b PAY-37/PAY-44, blueprint §8.7. The ONLY writer of
// payment_http_traces. It records REAL request/response pairs (never
// templates): the caller passes the request it actually sent and the
// response it actually received.
//
// PAY-44: sanitization happens HERE, before anything is stored.
// Header values are dropped (name kept, value replaced with
// '[disembunyikan]') when the name hints at a credential
// (authorization, cookie, secret, signature, token, key) — so the
// webhook signature value is never stored. Body keys hinting at a
// secret/password/signature/cookie are dropped the same way. The
// payment_token is deliberately kept when it appears inside a
// simulator URL or instruction body: it is part of the payment
// instruction, not a credential, and the M3c panel needs it to show
// the QR URL. The webhook secret and APP_KEY never appear in any
// header or body that reaches this table.
class TraceRecorder
{
    public function record(
        string $kind,
        ?Payment $payment,
        ?int $orderId,
        string $method,
        string $path,
        array $headers,
        mixed $body,
        int $responseStatus,
        mixed $responseBody,
        ?string $requestId = null,
    ): PaymentHttpTrace {
        return PaymentHttpTrace::create([
            'payment_id' => $payment?->id,
            'order_id' => $orderId,
            'request_id' => $requestId ?? (string) Str::uuid(),
            'kind' => $kind,
            'method' => $method,
            'path' => $path,
            'request_headers_json' => $this->sanitizeHeaders($headers),
            'request_body_json' => $this->sanitizeBody($body),
            'response_status' => $responseStatus,
            'response_body_json' => $this->sanitizeBody($responseBody),
            'occurred_at' => now(),
            'origin' => $payment?->origin ?? 'normal',
            'simulation_run_id' => $payment?->simulation_run_id,
        ]);
    }

    /** @return array<string, mixed> */
    private function sanitizeHeaders(array $headers): array
    {
        $out = [];

        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);

            if (str_contains($lower, 'authorization')
                || str_contains($lower, 'cookie')
                || str_contains($lower, 'secret')
                || str_contains($lower, 'signature')
                || str_contains($lower, 'token')
                || str_contains($lower, 'key')
            ) {
                $out[(string) $name] = '[disembunyikan]';
            } else {
                $out[(string) $name] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $out;
    }

    private function sanitizeBody(mixed $body): mixed
    {
        if (! is_array($body)) {
            return $body;
        }

        $out = [];

        foreach ($body as $key => $value) {
            $lower = strtolower((string) $key);

            if (str_contains($lower, 'secret')
                || str_contains($lower, 'password')
                || str_contains($lower, 'signature')
                || str_contains($lower, 'cookie')
            ) {
                $out[$key] = '[disembunyikan]';
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
