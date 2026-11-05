<?php

namespace App\Payments;

// M3b PAY-58, blueprint §8.3. The QR payload: `type` is 'url' for the
// simulator (the QR encodes the simulator-page URL) or 'raw' for a
// future real provider that returns its own payload. The instruction
// page only calls QrRenderer::render(QrPayload); it never inspects
// the value.
final class QrPayload
{
    public function __construct(
        public string $type, // 'url' | 'raw'
        public string $value,
    ) {}

    // PAY-06: the simulator-page URL encoded in the QR. Built from
    // config('app.url') — i.e. APP_URL — on purpose, NOT from url():
    // the QR leaves the device that made the request (it is scanned
    // by a phone), so it must carry the canonical address, not the
    // current request's host. url() follows the request host, and
    // inside the internal dispatch the sub-request host is always
    // localhost, so APP_URL was never consulted before this fix.
    // Single source of truth: the gateway and PaymentService's
    // fallback both use this method.
    public static function simPageUrl(string $token): string
    {
        return rtrim((string) config('app.url'), '/').'/pay/sim/'.$token;
    }
}
