<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentStatusTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentStatusTransition>
 */
class PaymentStatusTransitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'from_status' => null,
            'to_status' => 'pending',
            'trigger' => 'checkout',
            'request_id' => null,
            'occurred_at' => now(),
            'origin' => 'normal',
            'simulation_run_id' => null,
            'created_at' => now(),
        ];
    }
}
