<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => 'penyesuaian',
            'delta_on_hand' => 0,
            'delta_reserved' => 0,
            'order_id' => null,
            'actor_type' => 'admin_toko',
            'actor_id' => User::factory()->admin(),
            'note' => null,
            'origin' => 'normal',
            'simulation_run_id' => null,
            'created_at' => now(),
        ];
    }
}
