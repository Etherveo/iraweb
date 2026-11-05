<?php

namespace App\Payments;

// M3b PAY-55/PAY-58, blueprint §8.1/§8.3. What the buyer needs to pay:
// the reference (VA number or QR reference code), the QR payload
// (simulator: always type 'url', the simulator-page URL), the VA
// number (VA methods only), the payment token (simulator only), and
// the expiry. The instruction page only renders this object; it never
// builds QR/VA values itself.
final class PaymentInstruction
{
    public function __construct(
        public string $paymentId,
        public string $referenceCode,
        public ?QrPayload $qr,
        public ?string $vaNumber,
        public ?string $paymentToken,
        public \DateTimeImmutable $expiresAt,
    ) {}
}
