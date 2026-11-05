<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'pembeli',
            'origin' => 'normal',
            'is_active' => true,
            'simulation_run_id' => null,
            // M4 slice 1 (buyer profile, Data_Pembeli §1). Every factory
            // user gets a profile; username is the name slugged plus a
            // unique number (same pattern the simulator will use).
            'username' => function (array $attributes) {
                return Str::slug($attributes['name'], '') . fake()->unique()->numberBetween(1, 9999);
            },
            'phone' => '08'.fake()->numerify('##########'),
            'gender' => fake()->randomElement(['laki-laki', 'perempuan']),
            'birthdate' => fake()->dateTimeBetween('-60 years', '-17 years')->format('Y-m-d'),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'admin_toko']);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    public function simulation(?string $runId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'origin' => 'developer_simulation',
            'simulation_run_id' => $runId ?? 'run-test-1',
            'email' => fake()->unique()->userName().'@'.config('ira.sim_account_email_domain'),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
