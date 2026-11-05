<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => null,
            'to_status' => 'menunggu_pembayaran',
            'trigger' => 'pembeli',
            'actor_type' => 'pembeli',
            'actor_id' => null,
            'reason' => null,
            'occurred_at' => now(),
            'origin' => 'normal',
            'simulation_run_id' => null,
            'created_at' => now(),
        ];
    }
}
