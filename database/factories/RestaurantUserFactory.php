<?php

namespace Database\Factories;

use App\Models\RestaurantUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantUser>
 */
class RestaurantUserFactory extends Factory
{
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
            'phone' => fake()->phoneNumber(),
            'password' => 'password',
            'status' => 'active',
        ];
    }
}
