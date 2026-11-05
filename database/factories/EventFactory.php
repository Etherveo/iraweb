<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_type' => 'view',
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'session_ref' => null,
            'order_id' => null,
            'order_item_id' => null,
            'reason' => null,
            'occurred_at' => now(),
            'origin' => 'normal',
            'simulation_run_id' => null,
        ];
    }
}
