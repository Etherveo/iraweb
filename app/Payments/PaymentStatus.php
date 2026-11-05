<?php

namespace App\Payments;

// M3b PAY-55, blueprint §8.1. The provider-side status of one
// payment, as returned by PaymentAdapter::getPaymentStatus.
final class PaymentStatus
{
    public function __construct(
        public string $status,
        public ?\DateTimeImmutable $paidAt = null,
        public ?string $reference = null,
    ) {}
}
