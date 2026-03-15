<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Place>
 */
class PlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Home', 'Work', 'Gym', 'Supercharger']),
            'latitude' => fake()->latitude(37, 38),
            'longitude' => fake()->longitude(-122, -121),
            'radius_meters' => fake()->numberBetween(50, 500),
            'electricity_cost_per_kwh' => fake()->randomFloat(4, 0.05, 0.40),
        ];
    }
}
