<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Default address is Gorontalo-flavoured (the store's city). The
     * recipient name is copied from the owning user when the user is
     * known at factory time (explicit user_id, or a user created by the
     * nested factory before this attribute is expanded); otherwise a
     * fake name is used.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipient_name' => function (array $attributes) {
                $user = isset($attributes['user_id']) ? User::find($attributes['user_id']) : null;

                return $user?->name ?? fake()->name();
            },
            'recipient_phone' => '08'.fake()->numerify('##########'),
            'province' => 'Gorontalo',
            'city' => 'Kota Gorontalo',
            'district' => 'Kota Selatan',
            'village' => 'Limba B',
            'postal_code' => '96115',
            'street_address' => 'Jl. Jend. Sudirman No. '.fake()->numberBetween(1, 200),
            'address_type' => 'Rumah',
        ];
    }
}
