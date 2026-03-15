<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Drive>
 */
class DriveFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 hour');
        $endedAt = (clone $startedAt)->modify('+' . fake()->numberBetween(5, 120) . ' minutes');
        $distance = fake()->randomFloat(1, 1, 100);
        $energyUsed = $distance * fake()->randomFloat(2, 0.2, 0.4);

        return [
            'vehicle_id' => Vehicle::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'distance' => $distance,
            'energy_used_kwh' => $energyUsed,
            'efficiency' => $energyUsed > 0 && $distance > 0 ? ($energyUsed * 1000) / $distance : null,
            'start_latitude' => fake()->latitude(37, 38),
            'start_longitude' => fake()->longitude(-122, -121),
            'end_latitude' => fake()->latitude(37, 38),
            'end_longitude' => fake()->longitude(-122, -121),
            'start_address' => fake()->address(),
            'end_address' => fake()->address(),
            'start_battery_level' => $startLevel = fake()->numberBetween(40, 95),
            'end_battery_level' => $startLevel - fake()->numberBetween(5, 30),
            'start_rated_range' => fake()->randomFloat(1, 100, 300),
            'end_rated_range' => fake()->randomFloat(1, 50, 250),
            'start_odometer' => $odom = fake()->randomFloat(1, 1000, 80000),
            'end_odometer' => $odom + $distance,
            'max_speed' => fake()->numberBetween(30, 85),
            'avg_speed' => fake()->numberBetween(20, 65),
            'outside_temp_avg' => fake()->randomFloat(1, -5, 40),
        ];
    }
}
