<?php

namespace App\Payments;

use App\Models\Payment;

// M3b PAY-55, blueprint §8.1/§8.6. The structured outcome of a
// callback, so the webhook controller only maps it to HTTP. `result`
// is one of: processed, already_processed, conflict, unauthorized,
// invalid, not_found. `requestId` is generated once per callback by
// the adapter and is shared by the payment transition rows and the
// HTTP trace, so one callback can be followed end to end.
final class CallbackResult
{
    public function __construct(
        public string $result,
        public ?Payment $payment = null,
        public ?string $orderStatus = null,
        public ?string $message = null,
        public ?string $requestId = null,
    ) {}

    public function httpStatus(): int
    {
        return match ($this->result) {
            'processed', 'already_processed' => 200,
            'unauthorized' => 401,
            'not_found' => 404,
            'conflict' => 409,
            default => 422,
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $body = ['result' => $this->result];

        if ($this->payment !== null) {
            $body['payment_status'] = $this->payment->status;
        }

        if ($this->orderStatus !== null) {
            $body['order_status'] = $this->orderStatus;
        }

        if ($this->message !== null) {
            $body['message'] = $this->message;
        }

        return $body;
    }
}
