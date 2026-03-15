<?php

namespace Tests\Feature\Api;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_list_places(): void
    {
        Place::factory()->count(3)->create(['user_id' => $this->user->id]);
        Place::factory()->create(); // another user

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/places')
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_create_place(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/places', [
                'name' => 'Home',
                'latitude' => 37.7749,
                'longitude' => -122.4194,
                'radius_meters' => 100,
                'electricity_cost_per_kwh' => 0.12,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Home']);
    }

    public function test_create_place_validates_coordinates(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/places', [
                'name' => 'Invalid',
                'latitude' => 91,
                'longitude' => -122,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('latitude');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/places', [
                'name' => 'Invalid',
                'latitude' => 37,
                'longitude' => 181,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('longitude');
    }

    public function test_create_place_validates_radius(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/places', [
                'name' => 'Test',
                'latitude' => 37,
                'longitude' => -122,
                'radius_meters' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('radius_meters');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/places', [
                'name' => 'Test',
                'latitude' => 37,
                'longitude' => -122,
                'radius_meters' => 6000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('radius_meters');
    }

    public function test_show_place(): void
    {
        $place = Place::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/places/{$place->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $place->id]);
    }

    public function test_cannot_show_other_users_place(): void
    {
        $place = Place::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/places/{$place->id}")
            ->assertStatus(403);
    }

    public function test_update_place(): void
    {
        $place = Place::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/places/{$place->id}", ['name' => 'Office'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Office']);
    }

    public function test_delete_place(): void
    {
        $place = Place::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/places/{$place->id}")
            ->assertOk();

        $this->assertDatabaseMissing('places', ['id' => $place->id]);
    }

    public function test_cannot_delete_other_users_place(): void
    {
        $place = Place::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/places/{$place->id}")
            ->assertStatus(403);
    }
}
