<?php

namespace Tests\Feature\Api;

use App\Models\Idle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdleTest extends TestCase
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

    public function test_list_idles(): void
    {
        Idle::factory()->count(3)->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/idles')
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 3);
    }

    public function test_list_idles_excludes_other_users(): void
    {
        Idle::factory()->count(2)->create(['vehicle_id' => $this->vehicle->id]);
        Idle::factory()->create(); // another user

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/idles')
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 2);
    }

    public function test_filter_idles_by_vehicle(): void
    {
        $vehicle2 = Vehicle::factory()->create(['user_id' => $this->user->id]);
        Idle::factory()->count(2)->create(['vehicle_id' => $this->vehicle->id]);
        Idle::factory()->create(['vehicle_id' => $vehicle2->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/idles?vehicle_id={$this->vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 2);
    }

    public function test_show_idle(): void
    {
        $idle = Idle::factory()->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/idles/{$idle->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $idle->id]);
    }

    public function test_cannot_show_other_users_idle(): void
    {
        $idle = Idle::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/idles/{$idle->id}")
            ->assertStatus(403);
    }

    public function test_pagination_works(): void
    {
        Idle::factory()->count(30)->create(['vehicle_id' => $this->vehicle->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/idles?per_page=10')
            ->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(30, $response->json('total'));
    }

    public function test_per_page_capped_at_100(): void
    {
        Idle::factory()->count(3)->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/idles?per_page=999')
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }

    public function test_idles_require_auth(): void
    {
        $this->getJson('/api/v1/idles')->assertStatus(401);
    }
}
