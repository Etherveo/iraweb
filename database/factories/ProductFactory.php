<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'price_idr' => fake()->numberBetween(1000, 100000),
            'stock_on_hand' => fake()->numberBetween(0, 100),
            'stock_reserved' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock_on_hand' => 0, 'stock_reserved' => 0]);
    }

    public function reserved(int $reserved): static
    {
        return $this->state(function (array $attributes) use ($reserved) {
            $onHand = max((int) $attributes['stock_on_hand'], $reserved);

            return ['stock_on_hand' => $onHand, 'stock_reserved' => $reserved];
        });
    }
}
