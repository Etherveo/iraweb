<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentHttpTrace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentHttpTrace>
 */
class PaymentHttpTraceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'order_id' => Order::factory(),
            'request_id' => (string) Str::uuid(),
            'kind' => 'callback',
            'method' => 'POST',
            'path' => '/api/v1/webhooks/payments/simulator',
            'request_headers_json' => ['Content-Type' => 'application/json'],
            'request_body_json' => ['status' => 'paid'],
            'response_status' => 200,
            'response_body_json' => ['result' => 'processed'],
            'occurred_at' => now(),
            'origin' => 'normal',
            'simulation_run_id' => null,
        ];
    }
}
