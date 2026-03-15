<?php

namespace Tests\Feature\Api;

use App\Models\FirmwareHistory;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirmwareTest extends TestCase
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

    public function test_list_firmware_history(): void
    {
        FirmwareHistory::create([
            'vehicle_id' => $this->vehicle->id,
            'version' => '2024.38.7',
            'detected_at' => now()->subDays(10),
            'previous_version' => '2024.32.5',
        ]);
        FirmwareHistory::create([
            'vehicle_id' => $this->vehicle->id,
            'version' => '2024.44.2',
            'detected_at' => now()->subDay(),
            'previous_version' => '2024.38.7',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$this->vehicle->id}/firmware-history")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_firmware_history_ordered_by_detected_at_desc(): void
    {
        FirmwareHistory::create([
            'vehicle_id' => $this->vehicle->id,
            'version' => '2024.32.5',
            'detected_at' => now()->subDays(20),
        ]);
        FirmwareHistory::create([
            'vehicle_id' => $this->vehicle->id,
            'version' => '2024.44.2',
            'detected_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$this->vehicle->id}/firmware-history")
            ->assertOk();

        $versions = collect($response->json())->pluck('version')->toArray();
        $this->assertEquals('2024.44.2', $versions[0]);
        $this->assertEquals('2024.32.5', $versions[1]);
    }

    public function test_cannot_access_other_users_firmware_history(): void
    {
        $otherVehicle = Vehicle::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$otherVehicle->id}/firmware-history")
            ->assertStatus(403);
    }

    public function test_empty_firmware_history(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$this->vehicle->id}/firmware-history")
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_firmware_history_requires_auth(): void
    {
        $this->getJson("/api/v1/vehicles/{$this->vehicle->id}/firmware-history")
            ->assertStatus(401);
    }
}
