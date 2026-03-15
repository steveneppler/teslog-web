<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tesla_vehicle_id' => fake()->unique()->randomNumber(9),
            'vin' => strtoupper(fake()->bothify('5YJ3E1EA##F######')),
            'name' => fake()->randomElement(['Model 3', 'Model Y', 'Model S', 'Model X']),
            'model' => fake()->randomElement(['model3', 'modely', 'models', 'modelx']),
            'trim' => fake()->randomElement(['Standard Range', 'Long Range', 'Performance']),
            'battery_capacity_kwh' => fake()->randomElement([57.5, 75, 82, 100]),
            'color' => fake()->randomElement(['Pearl White', 'Solid Black', 'Midnight Silver', 'Deep Blue', 'Red']),
            'firmware_version' => '2024.38.7',
            'is_active' => true,
            'show_on_dashboard' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
