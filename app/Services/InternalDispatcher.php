<?php

namespace App\Services;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// M3b PAY-36, blueprint §8.5. Sends a request to the application's
// OWN routes through the real HTTP kernel (same routing, same
// middleware, same controller as a real external request), then
// restores the outer request afterwards.
//
// This is the ONLY transport for simulator provider events: the sim
// page's Pay/Fail actions and the expiry job's expired callback do
// NOT call PaymentCallbackHandler directly — they build a signed
// payload here and use the webhook endpoint's response. The M3c panel
// therefore shows events that really happened.
//
// B-01 risk: a sub-request inside a running request can disturb
// global state. The dispatcher restores the outer request instance
// (session, CSRF token, route bindings) in a finally block, and the
// sub-request never touches the outer session (API routes are
// stateless). Regression tests assert the outer request is unchanged
// after a dispatch.
class InternalDispatcher
{
    // Low-level dispatch: $body is the EXACT byte string that is sent
    // (and, for webhooks, the exact string the HMAC was computed over).
    public function dispatch(
        string $method,
        string $uri,
        string $body,
        array $headers = [],
        array $attributes = [],
    ): Response {
        $request = Request::create($uri, $method, [], [], [], [
            'REQUEST_METHOD' => strtoupper($method),
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
        ], $body);

        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        foreach ($attributes as $key => $value) {
            $request->attributes->set($key, $value);
        }

        $kernel = app(HttpKernel::class);
        $outer = request();

        try {
            return $kernel->handle($request);
        } finally {
            // Restore the outer request no matter what happened inside.
            app()->instance('request', $outer);
        }
    }

    // Signed dispatch to the webhook endpoint (PAY-24/PAY-36). The
    // signature is computed over the exact JSON bytes that are sent.
    public function dispatchWebhook(string $provider, array $payload): Response
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = \App\Payments\SignatureVerifier::sign(
            $body,
            (string) config('payment.webhook_secret', ''),
        );

        return $this->dispatch('POST', '/api/v1/webhooks/payments/'.$provider, $body, [
            \App\Payments\SignatureVerifier::HEADER => $signature,
        ]);
    }

    // Dispatch to the internal simulator gateway (blueprint §8.4). The
    // request carries the `internal_dispatch` attribute, which only
    // this dispatcher can set; the InternalDispatchOnly middleware
    // rejects any real external request to the same route.
    public function dispatchSimulatorPayment(array $payload): Response
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->dispatch('POST', '/api/v1/simulator/payments', $body, [], [
            'internal_dispatch' => true,
        ]);
    }
}
