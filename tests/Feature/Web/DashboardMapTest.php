<?php

namespace Tests\Feature\Web;

use App\Livewire\Dashboard;
use App\Models\Drive;
use App\Models\DrivePoint;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $timezone = 'America/Los_Angeles'): User
    {
        return User::factory()->create(['timezone' => $timezone]);
    }

    public function test_map_of_the_day_shows_todays_drive_route(): void
    {
        $user = $this->user();
        $vehicle = Vehicle::factory()->for($user)->create();

        $startedAt = now()->tz($user->userTz())->startOfDay()->addHours(9);
        $drive = Drive::factory()->for($vehicle)->create([
            'started_at' => $startedAt->copy()->utc(),
            'ended_at' => $startedAt->copy()->addMinutes(30)->utc(),
            'distance' => 12.5,
        ]);

        foreach (range(0, 4) as $i) {
            DrivePoint::create([
                'drive_id' => $drive->id,
                'timestamp' => $startedAt->copy()->addMinutes($i)->utc(),
                'latitude' => 37.4 + ($i * 0.01),
                'longitude' => -122.1 + ($i * 0.01),
            ]);
        }

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Map of the Day')
            ->assertSee('dashboard-day-map', false)
            ->assertDispatched('day-map-updated', function ($event, $params) {
                return count($params['routes']) === 1
                    && count($params['routes'][0]['coords']) === 5
                    && $params['routes'][0]['coords'][0] === [37.4, -122.1];
            });
    }

    public function test_map_of_the_day_excludes_drives_from_other_days(): void
    {
        $user = $this->user();
        $vehicle = Vehicle::factory()->for($user)->create();

        $yesterday = now()->tz($user->userTz())->startOfDay()->subHours(3);
        $drive = Drive::factory()->for($vehicle)->create([
            'started_at' => $yesterday->copy()->utc(),
            'ended_at' => $yesterday->copy()->addMinutes(20)->utc(),
        ]);

        DrivePoint::create([
            'drive_id' => $drive->id,
            'timestamp' => $yesterday->copy()->utc(),
            'latitude' => 37.4,
            'longitude' => -122.1,
        ]);
        DrivePoint::create([
            'drive_id' => $drive->id,
            'timestamp' => $yesterday->copy()->addMinutes(5)->utc(),
            'latitude' => 37.5,
            'longitude' => -122.2,
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertDispatched('day-map-updated', fn ($event, $params) => $params['routes'] === []);
    }

    public function test_map_of_the_day_falls_back_to_last_known_location(): void
    {
        $user = $this->user();
        $vehicle = Vehicle::factory()->for($user)->create();

        $state = VehicleState::factory()->for($vehicle)->create([
            'timestamp' => now()->subMinutes(10),
            'latitude' => 37.42,
            'longitude' => -122.08,
            'state' => 'idle',
        ]);
        $vehicle->update(['latest_state_id' => $state->id]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('showing the last known location')
            ->assertDispatched('day-map-updated', function ($event, $params) {
                return $params['routes'] === []
                    && count($params['locations']) === 1
                    && $params['locations'][0]['lat'] === 37.42;
            });
    }

    public function test_map_of_the_day_hidden_without_any_location_data(): void
    {
        $user = $this->user();
        Vehicle::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('No location data for today yet.')
            ->assertDontSee('dashboard-day-map', false);
    }
}
