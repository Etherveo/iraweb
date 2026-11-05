<?php

namespace App\Payments;

// M3b PAY-25, blueprint §8.6. The dummy HMAC signature for simulator
// webhooks: `sim-hmac-sha256=<hex>` over the RAW request body, keyed
// by SIMULATOR_WEBHOOK_SECRET from the environment. Compared in
// constant time. An empty secret never verifies (fail closed): if the
// secret is not configured, every callback is rejected with 401.
// The secret itself is never logged, displayed, or stored anywhere.
class SignatureVerifier
{
    public const HEADER = 'X-Simulator-Signature';

    public const PREFIX = 'sim-hmac-sha256=';

    public static function sign(string $rawBody, string $secret): string
    {
        return self::PREFIX.hash_hmac('sha256', $rawBody, $secret);
    }

    public static function verify(string $rawBody, ?string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($rawBody, $secret), $signature);
    }
}
