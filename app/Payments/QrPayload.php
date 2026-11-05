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
}
