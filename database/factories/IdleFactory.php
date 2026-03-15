<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Idle>
 */
class IdleFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 hour');
        $endedAt = (clone $startedAt)->modify('+' . fake()->numberBetween(30, 720) . ' minutes');

        return [
            'vehicle_id' => Vehicle::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'latitude' => fake()->latitude(37, 38),
            'longitude' => fake()->longitude(-122, -121),
            'address' => fake()->address(),
            'start_battery_level' => $start = fake()->numberBetween(50, 95),
            'end_battery_level' => $start - fake()->numberBetween(0, 5),
            'vampire_drain_rate' => fake()->randomFloat(2, 0, 2),
            'sentry_mode_active' => fake()->boolean(20),
        ];
    }
}
