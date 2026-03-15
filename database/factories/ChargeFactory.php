<?php

namespace Database\Factories;

use App\Enums\ChargeType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Charge>
 */
class ChargeFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 hour');
        $endedAt = (clone $startedAt)->modify('+' . fake()->numberBetween(30, 480) . ' minutes');

        return [
            'vehicle_id' => Vehicle::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'charge_type' => fake()->randomElement(ChargeType::cases()),
            'energy_added_kwh' => fake()->randomFloat(2, 5, 70),
            'cost' => fake()->randomFloat(2, 1, 30),
            'start_battery_level' => fake()->numberBetween(10, 50),
            'end_battery_level' => fake()->numberBetween(60, 100),
            'start_rated_range' => fake()->randomFloat(1, 30, 150),
            'end_rated_range' => fake()->randomFloat(1, 150, 310),
            'max_charger_power' => fake()->randomElement([7, 11, 48, 120, 150, 250]),
            'latitude' => fake()->latitude(37, 38),
            'longitude' => fake()->longitude(-122, -121),
            'address' => fake()->address(),
        ];
    }
}
