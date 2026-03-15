<?php

namespace Tests\Feature\Api;

use App\Models\Charge;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeTest extends TestCase
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

    public function test_list_charges(): void
    {
        Charge::factory()->count(3)->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/charges')
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 3);
    }

    public function test_list_charges_excludes_other_users(): void
    {
        Charge::factory()->count(2)->create(['vehicle_id' => $this->vehicle->id]);
        Charge::factory()->create(); // another user

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/charges')
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 2);
    }

    public function test_filter_charges_by_type(): void
    {
        Charge::factory()->create(['vehicle_id' => $this->vehicle->id, 'charge_type' => 'ac']);
        Charge::factory()->create(['vehicle_id' => $this->vehicle->id, 'charge_type' => 'dc']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/charges?charge_type=ac')
            ->assertOk()
            ->assertJsonPath('data', fn ($data) => count($data) === 1);
    }

    public function test_per_page_capped_at_100(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/charges?per_page=999')
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }

    public function test_show_charge(): void
    {
        $charge = Charge::factory()->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/charges/{$charge->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $charge->id]);
    }

    public function test_cannot_show_other_users_charge(): void
    {
        $charge = Charge::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/charges/{$charge->id}")
            ->assertStatus(403);
    }

    public function test_update_charge_tag(): void
    {
        $charge = Charge::factory()->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/charges/{$charge->id}", ['tag' => 'road-trip'])
            ->assertOk()
            ->assertJsonFragment(['tag' => 'road-trip']);
    }

    public function test_cannot_update_other_users_charge(): void
    {
        $charge = Charge::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/charges/{$charge->id}", ['tag' => 'hacked'])
            ->assertStatus(403);
    }

    public function test_charges_require_auth(): void
    {
        $this->getJson('/api/v1/charges')->assertStatus(401);
    }
}
