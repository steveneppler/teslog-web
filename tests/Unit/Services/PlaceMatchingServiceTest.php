<?php

namespace Tests\Unit\Services;

use App\Models\Place;
use App\Models\User;
use App\Services\PlaceMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlaceMatchingService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlaceMatchingService();
        $this->user = User::factory()->create();
    }

    public function test_haversine_distance_known_coordinates(): void
    {
        // San Francisco to Oakland (~13 km)
        $distance = $this->service->haversineDistance(37.7749, -122.4194, 37.8044, -122.2712);

        // Should be approximately 13,400 meters
        $this->assertGreaterThan(13000, $distance);
        $this->assertLessThan(14000, $distance);
    }

    public function test_haversine_distance_same_point_is_zero(): void
    {
        $distance = $this->service->haversineDistance(37.7749, -122.4194, 37.7749, -122.4194);
        $this->assertEquals(0.0, $distance);
    }

    public function test_find_match_returns_closest_place_within_radius(): void
    {
        $place = Place::factory()->create([
            'user_id' => $this->user->id,
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'radius_meters' => 500,
        ]);

        // Point ~100 meters away
        $result = $this->service->findMatch($this->user->id, 37.7758, -122.4194);

        $this->assertNotNull($result);
        $this->assertEquals($place->id, $result->id);
    }

    public function test_find_match_returns_null_when_no_places_within_radius(): void
    {
        Place::factory()->create([
            'user_id' => $this->user->id,
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'radius_meters' => 100,
        ]);

        // Point ~13 km away (Oakland)
        $result = $this->service->findMatch($this->user->id, 37.8044, -122.2712);

        $this->assertNull($result);
    }

    public function test_find_match_returns_closest_when_multiple_match(): void
    {
        $farPlace = Place::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Far Place',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'radius_meters' => 1000,
        ]);

        $closePlace = Place::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Close Place',
            'latitude' => 37.7755,
            'longitude' => -122.4190,
            'radius_meters' => 1000,
        ]);

        // Point very close to "Close Place"
        $result = $this->service->findMatch($this->user->id, 37.7756, -122.4190);

        $this->assertNotNull($result);
        $this->assertEquals($closePlace->id, $result->id);
    }

    public function test_find_match_returns_null_for_null_coordinates(): void
    {
        Place::factory()->create(['user_id' => $this->user->id]);

        $this->assertNull($this->service->findMatch($this->user->id, null, null));
    }

    public function test_find_match_ignores_other_users_places(): void
    {
        $otherUser = User::factory()->create();
        Place::factory()->create([
            'user_id' => $otherUser->id,
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'radius_meters' => 500,
        ]);

        $result = $this->service->findMatch($this->user->id, 37.7749, -122.4194);

        $this->assertNull($result);
    }
}
