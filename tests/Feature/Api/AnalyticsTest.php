<?php

namespace Tests\Feature\Api;

use App\Models\Charge;
use App\Models\Drive;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
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

    public function test_efficiency_endpoint(): void
    {
        Drive::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'started_at' => now()->subDays(5),
            'efficiency' => 250,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/efficiency')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_efficiency_days_capped_at_365(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/efficiency?days=9999')
            ->assertOk();
    }

    public function test_energy_endpoint(): void
    {
        Drive::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'started_at' => now()->subDays(5),
            'energy_used_kwh' => 20,
        ]);
        Charge::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'started_at' => now()->subDays(5),
            'energy_added_kwh' => 30,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/energy')
            ->assertOk()
            ->assertJsonStructure(['energy_used', 'energy_added']);
    }

    public function test_cost_endpoint(): void
    {
        Charge::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'started_at' => now()->subDays(5),
            'cost' => 15.50,
            'charge_type' => 'ac',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/cost')
            ->assertOk()
            ->assertJsonStructure(['by_type', 'monthly']);
    }

    public function test_calendar_endpoint(): void
    {
        $month = now()->format('Y-m');

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/analytics/calendar?month={$month}")
            ->assertOk()
            ->assertJsonStructure(['month', 'drives', 'charges', 'idles']);
    }

    public function test_calendar_validates_month_format(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/calendar?month=not-a-month')
            ->assertStatus(422);
    }

    public function test_calendar_rejects_sql_injection_in_month(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/analytics/calendar?month=2024-01;DROP TABLE drives')
            ->assertStatus(422);
    }

    public function test_analytics_require_auth(): void
    {
        $this->getJson('/api/v1/analytics/efficiency')->assertStatus(401);
        $this->getJson('/api/v1/analytics/energy')->assertStatus(401);
        $this->getJson('/api/v1/analytics/cost')->assertStatus(401);
        $this->getJson('/api/v1/analytics/calendar')->assertStatus(401);
    }
}
