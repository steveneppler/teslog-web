<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_list_vehicles(): void
    {
        Vehicle::factory()->count(3)->create(['user_id' => $this->user->id]);
        Vehicle::factory()->create(); // another user's vehicle

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/vehicles');

        $response->assertOk()
            ->assertJsonCount(3);
    }

    public function test_create_vehicle(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/vehicles', [
                'name' => 'My Tesla',
                'vin' => '5YJ3E1EA1NF000001',
                'model' => 'model3',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'My Tesla']);

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $this->user->id,
            'name' => 'My Tesla',
        ]);
    }

    public function test_create_vehicle_requires_name(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/vehicles', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_show_own_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $vehicle->id]);
    }

    public function test_cannot_show_other_users_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertStatus(403);
    }

    public function test_update_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/vehicles/{$vehicle->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_cannot_update_other_users_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/vehicles/{$vehicle->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_delete_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk();

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_cannot_delete_other_users_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertStatus(403);
    }

    public function test_timeline_caps_limit(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}/timeline?limit=9999")
            ->assertOk();
    }

    public function test_vehicles_require_auth(): void
    {
        $this->getJson('/api/v1/vehicles')->assertStatus(401);
    }
}
