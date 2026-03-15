<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\TeslaCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CommandTest extends TestCase
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

    public function test_cannot_send_command_to_other_users_vehicle(): void
    {
        $otherVehicle = Vehicle::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$otherVehicle->id}/commands/lock")
            ->assertStatus(403);
    }

    public function test_unknown_command_returns_400(): void
    {
        $this->mock(TeslaCommandService::class);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$this->vehicle->id}/commands/self_destruct")
            ->assertStatus(400)
            ->assertJsonFragment(['error' => 'Unknown command']);
    }

    public function test_valid_command_calls_service(): void
    {
        $this->mock(TeslaCommandService::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn(['success' => true, 'result' => ['result' => true]]);
        });

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$this->vehicle->id}/commands/lock")
            ->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    public function test_command_requires_auth(): void
    {
        $this->postJson("/api/v1/vehicles/{$this->vehicle->id}/commands/lock")
            ->assertStatus(401);
    }
}
