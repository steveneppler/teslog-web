<?php

namespace Tests\Feature\Api;

use App\Models\Drive;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveTest extends TestCase
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

    public function test_list_drives(): void
    {
        Drive::factory()->count(3)->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/drives')
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 3);
    }

    public function test_list_drives_excludes_other_users(): void
    {
        Drive::factory()->count(2)->create(['vehicle_id' => $this->vehicle->id]);
        Drive::factory()->create(); // another user

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/drives')
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 2);
    }

    public function test_filter_drives_by_vehicle(): void
    {
        $vehicle2 = Vehicle::factory()->create(['user_id' => $this->user->id]);
        Drive::factory()->count(2)->create(['vehicle_id' => $this->vehicle->id]);
        Drive::factory()->create(['vehicle_id' => $vehicle2->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/drives?vehicle_id={$this->vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 2);
    }

    public function test_filter_drives_by_tag(): void
    {
        Drive::factory()->create(['vehicle_id' => $this->vehicle->id, 'tag' => 'commute']);
        Drive::factory()->create(['vehicle_id' => $this->vehicle->id, 'tag' => 'road-trip']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/drives?tag=commute')
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 1);
    }

    public function test_per_page_capped_at_100(): void
    {
        Drive::factory()->count(3)->create(['vehicle_id' => $this->vehicle->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/drives?per_page=999');

        $response->assertOk()
            ->assertJsonPath('per_page', 100);
    }

    public function test_show_drive(): void
    {
        $drive = Drive::factory()->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/drives/{$drive->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $drive->id]);
    }

    public function test_cannot_show_other_users_drive(): void
    {
        $drive = Drive::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/drives/{$drive->id}")
            ->assertStatus(403);
    }

    public function test_update_drive_tag(): void
    {
        $drive = Drive::factory()->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/drives/{$drive->id}", ['tag' => 'commute'])
            ->assertOk()
            ->assertJsonFragment(['tag' => 'commute']);
    }

    public function test_cannot_update_other_users_drive(): void
    {
        $drive = Drive::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/drives/{$drive->id}", ['tag' => 'hacked'])
            ->assertStatus(403);
    }

    public function test_drives_require_auth(): void
    {
        $this->getJson('/api/v1/drives')->assertStatus(401);
    }
}
