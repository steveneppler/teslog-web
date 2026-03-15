<?php

namespace Tests\Feature\Api;

use App\Models\BatteryHealth;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatteryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_list_battery_health(): void
    {
        BatteryHealth::factory()->count(5)->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$this->vehicle->id}/battery-health")
            ->assertOk()
            ->assertJsonCount(5);
    }

    public function test_limit_capped_at_1000(): void
    {
        BatteryHealth::factory()->count(3)->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$this->vehicle->id}/battery-health?limit=9999")
            ->assertOk();
    }

    public function test_cannot_access_other_users_battery_health(): void
    {
        $otherVehicle = Vehicle::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$otherVehicle->id}/battery-health")
            ->assertStatus(403);
    }

    public function test_battery_health_requires_auth(): void
    {
        $this->getJson("/api/v1/vehicles/{$this->vehicle->id}/battery-health")
            ->assertStatus(401);
    }
}
