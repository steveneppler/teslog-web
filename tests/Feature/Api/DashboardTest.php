<?php

namespace Tests\Feature\Api;

use App\Models\Charge;
use App\Models\Drive;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dashboard_returns_vehicle_data(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);
        Drive::factory()->create(['vehicle_id' => $vehicle->id, 'started_at' => now()->subDays(5)]);
        Charge::factory()->create(['vehicle_id' => $vehicle->id, 'started_at' => now()->subDays(5)]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'vehicles',
                'recent_drives',
                'recent_charges',
                'stats' => [
                    'total_drives_30d',
                    'total_distance_30d',
                    'total_energy_used_30d',
                    'total_energy_added_30d',
                    'total_charge_cost_30d',
                ],
            ]);
    }

    public function test_dashboard_returns_empty_when_no_vehicles(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('vehicles', [])
            ->assertJsonPath('recent_drives', [])
            ->assertJsonPath('recent_charges', []);
    }

    public function test_dashboard_excludes_inactive_vehicles(): void
    {
        Vehicle::factory()->create(['user_id' => $this->user->id, 'is_active' => true]);
        Vehicle::factory()->inactive()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk();

        $this->assertCount(1, $response->json('vehicles'));
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
    }

    public function test_dashboard_excludes_other_users_data(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);
        Drive::factory()->create(['vehicle_id' => $vehicle->id, 'started_at' => now()->subDay()]);
        Drive::factory()->create(['started_at' => now()->subDay()]); // other user

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk();

        $this->assertEquals(1, $response->json('stats.total_drives_30d'));
    }
}
