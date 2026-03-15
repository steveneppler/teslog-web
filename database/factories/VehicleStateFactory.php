<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleState>
 */
class VehicleStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'timestamp' => fake()->dateTimeBetween('-7 days', 'now'),
            'state' => 'idle',
            'latitude' => fake()->latitude(37, 38),
            'longitude' => fake()->longitude(-122, -121),
            'heading' => fake()->numberBetween(0, 359),
            'speed' => 0,
            'power' => 0,
            'battery_level' => fake()->numberBetween(20, 95),
            'rated_range' => fake()->randomFloat(1, 50, 310),
            'odometer' => fake()->randomFloat(1, 1000, 80000),
            'inside_temp' => fake()->randomFloat(1, 18, 28),
            'outside_temp' => fake()->randomFloat(1, -5, 40),
            'locked' => true,
            'sentry_mode' => false,
            'climate_on' => false,
        ];
    }

    public function driving(): static
    {
        return $this->state(fn () => [
            'state' => 'driving',
            'speed' => fake()->numberBetween(20, 85),
            'gear' => 'D',
            'power' => fake()->numberBetween(-20, 80),
        ]);
    }

    public function charging(): static
    {
        return $this->state(fn () => [
            'state' => 'charging',
            'speed' => 0,
            'charge_state' => 'Charging',
            'charger_power' => fake()->randomElement([7, 11, 48, 150, 250]),
        ]);
    }
}
