<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BatteryHealth>
 */
class BatteryHealthFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'recorded_at' => fake()->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'battery_level' => fake()->numberBetween(70, 100),
            'rated_range' => fake()->randomFloat(1, 200, 310),
            'ideal_range' => fake()->randomFloat(1, 220, 340),
            'degradation_pct' => fake()->randomFloat(2, 0, 15),
        ];
    }
}
