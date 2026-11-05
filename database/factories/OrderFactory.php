<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => 'IRA-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
            'user_id' => User::factory(),
            'status' => 'menunggu_pembayaran',
            'total_idr' => fake()->numberBetween(1000, 100000),
            'payment_deadline_at' => now()->addMinutes(30),
            'paid_at' => null,
            'checkout_key' => (string) Str::uuid(),
            'origin' => 'normal',
            'simulation_run_id' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dibayar',
            'paid_at' => now(),
        ]);
    }
}
