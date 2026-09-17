<?php

namespace Tests\Feature\Web;

use App\Livewire\LifetimeMap;
use App\Models\Drive;
use App\Models\DrivePoint;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LifetimeMapOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_it_renders_the_display_options_panel(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(LifetimeMap::class)
            ->assertSee('Display options')
            ->assertSee('data-opt-weight', false)
            ->assertSee('data-opt-single-color', false)
            ->assertSee('data-opt-stats-overlay', false)
            ->assertSee('data-color-preset="#e82127"', false);
    }

    public function test_it_dispatches_overlay_stats_with_the_map_data(): void
    {
        $user = User::factory()->create(['distance_unit' => 'mi']);
        $vehicle = Vehicle::factory()->for($user)->create(['name' => 'Model 3']);

        $drive = Drive::factory()->for($vehicle)->create([
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
            'distance' => 42.0,
            'energy_used_kwh' => 12.0,
        ]);

        foreach (range(0, 3) as $i) {
            DrivePoint::create([
                'drive_id' => $drive->id,
                'timestamp' => now()->subHours(2)->addMinutes($i),
                'latitude' => 37.4 + ($i * 0.01),
                'longitude' => -122.1 + ($i * 0.01),
            ]);
        }

        Livewire::actingAs($user)->test(LifetimeMap::class)
            ->assertDispatched('lifetime-map-updated', function ($event, $params) {
                $labels = array_column($params['overlay']['items'], 'label');

                return $params['overlay']['title'] === 'Model 3'
                    && in_array('Distance', $labels)
                    && in_array('Drives', $labels);
            });
    }

    public function test_the_overlay_names_every_selected_vehicle(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->for($user)->create(['name' => 'Model 3']);
        Vehicle::factory()->for($user)->create(['name' => 'Model Y']);

        Livewire::actingAs($user)->test(LifetimeMap::class)
            ->assertDispatched('lifetime-map-updated', function ($event, $params) {
                return $params['overlay']['title'] === 'Model 3 + Model Y';
            });
    }
}
