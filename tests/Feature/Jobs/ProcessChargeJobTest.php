<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessChargeJob;
use App\Models\Charge;
use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Services\ChargeCostService;
use App\Services\GeocodingService;
use App\Services\PlaceMatchingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessChargeJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Geocoder makes external HTTP calls — fake them.
        Http::fake();
    }

    private function runJob(int $vehicleId, Carbon $endedAt): void
    {
        (new ProcessChargeJob($vehicleId, $endedAt))->handle(
            app(GeocodingService::class),
            app(PlaceMatchingService::class),
            app(ChargeCostService::class),
        );
    }

    public function test_brief_idle_island_within_charge_does_not_truncate_session_start(): void
    {
        $vehicle = Vehicle::factory()->create();
        $base = Carbon::parse('2026-04-15 10:00:00');

        // First charging state — captures the true charge start time.
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $base->copy(),
            'state' => 'charging',
            'battery_level' => 60.0,
            'energy_remaining' => 44.0,
            'charger_power' => 11.0,
            'rated_range' => 150.0,
            'latitude' => 39.0,
            'longitude' => -108.5,
        ]);

        // Brief 30-second idle island (the kind of transient state-detection
        // drop that happens at the start of an AC charge when charge_state
        // briefly leaves the active-charging allowlist).
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $base->copy()->addSeconds(30),
            'state' => 'idle',
            'battery_level' => 60.05,
            'energy_remaining' => 44.02,
            'charger_power' => 0.1,
        ]);

        // Resume charging for ~6 minutes.
        for ($i = 0; $i < 12; $i++) {
            VehicleState::factory()->create([
                'vehicle_id' => $vehicle->id,
                'timestamp' => $base->copy()->addSeconds(60 + $i * 30),
                'state' => 'charging',
                'battery_level' => 60.5 + $i * 0.5,
                'energy_remaining' => 44.5 + $i * 0.3,
                'charger_power' => 11.0,
                'rated_range' => 150.0,
                'latitude' => 39.0,
                'longitude' => -108.5,
            ]);
        }

        // Final transition to idle (end of charge).
        $endedAt = $base->copy()->addMinutes(7);
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $endedAt,
            'state' => 'idle',
            'battery_level' => 66.5,
            'energy_remaining' => 48.1,
            'charger_power' => 0,
        ]);

        $this->runJob($vehicle->id, $endedAt);

        $this->assertDatabaseCount('charges', 1);
        $charge = Charge::first();
        // The brief idle island must NOT have truncated the session start —
        // started_at must equal the very first charging state's timestamp.
        $this->assertEquals(
            $base->toDateTimeString(),
            $charge->started_at->toDateTimeString(),
        );
        $this->assertEquals(60.0, $charge->start_battery_level);
    }

    public function test_small_charge_with_immediate_drive_away_is_not_discarded_as_phantom(): void
    {
        // Real low-power charge that adds ~0.4% / ~0.3 kWh, then the user
        // immediately drives away. The post-session transition state is a
        // driving state with a slightly DEPLETED battery — using that for
        // the phantom check would mask the small real charge. The check
        // must compare first vs last *charging* state instead.
        $vehicle = Vehicle::factory()->create();
        $base = Carbon::parse('2026-04-15 12:00:00');

        // Charging block, 4 minutes, small but real net gain
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $base->copy(),
            'state' => 'charging',
            'battery_level' => 60.0,
            'energy_remaining' => 44.0,
            'charger_power' => 1.5,
            'rated_range' => 150.0,
            'latitude' => 39.0,
            'longitude' => -108.5,
        ]);
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $base->copy()->addMinutes(2),
            'state' => 'charging',
            'battery_level' => 60.2,
            'energy_remaining' => 44.15,
            'charger_power' => 1.5,
            'rated_range' => 150.5,
            'latitude' => 39.0,
            'longitude' => -108.5,
        ]);
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $base->copy()->addMinutes(4),
            'state' => 'charging',
            'battery_level' => 60.4,
            'energy_remaining' => 44.3,
            'charger_power' => 1.5,
            'rated_range' => 151.0,
            'latitude' => 39.0,
            'longitude' => -108.5,
        ]);

        // Driving away immediately — battery has already DEPLETED below
        // start_battery_level. If the phantom check used this state's
        // values, the session would look flat/negative.
        $endedAt = $base->copy()->addMinutes(4)->addSeconds(30);
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $endedAt,
            'state' => 'driving',
            'speed' => 25,
            'gear' => 'D',
            'battery_level' => 59.8,        // depleted below start (60.0)
            'energy_remaining' => 43.9,     // depleted below start (44.0)
            'charger_power' => 0,
        ]);

        $this->runJob($vehicle->id, $endedAt);

        // Charge is created — the phantom check correctly compared the
        // first vs last charging state (60.0 -> 60.4), not the depleted
        // post-drive transition state (59.8). End values are clamped to
        // the last charging reading so a quick drive-away doesn't push
        // end_battery_level / energy_added_kwh negative.
        $this->assertDatabaseCount('charges', 1);
        $charge = Charge::first();
        $this->assertEquals(60.0, $charge->start_battery_level);
        $this->assertEquals(60.4, $charge->end_battery_level);
        $this->assertEqualsWithDelta(0.3, $charge->energy_added_kwh, 0.001);
    }

    public function test_transition_state_with_higher_battery_is_used_for_end_values(): void
    {
        // Original purpose of the transition-state lookup: capture the
        // post-completion battery creep (battery often rises ~0.1% in the
        // seconds after the charger stops reporting). The clamp must
        // preserve this behavior — if the transition state's battery is
        // HIGHER than the last charging state, use the transition value.
        $vehicle = Vehicle::factory()->create();
        $base = Carbon::parse('2026-04-15 14:00:00');

        for ($i = 0; $i < 5; $i++) {
            VehicleState::factory()->create([
                'vehicle_id' => $vehicle->id,
                'timestamp' => $base->copy()->addMinutes($i),
                'state' => 'charging',
                'battery_level' => 70.0 + $i * 0.5,
                'energy_remaining' => 50.0 + $i * 0.3,
                'charger_power' => 11.0,
                'rated_range' => 175.0,
                'latitude' => 39.0,
                'longitude' => -108.5,
            ]);
        }
        // Last charging state at t+4: 72.0% / 51.2 kWh

        // Transition state (idle, post-completion) shows the creep: 72.1% / 51.3 kWh
        $endedAt = $base->copy()->addMinutes(4)->addSeconds(30);
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $endedAt,
            'state' => 'idle',
            'battery_level' => 72.1,
            'energy_remaining' => 51.3,
            'charger_power' => 0,
        ]);

        $this->runJob($vehicle->id, $endedAt);

        $this->assertDatabaseCount('charges', 1);
        $charge = Charge::first();
        // End values come from the transition state (the higher of the two)
        $this->assertEquals(72.1, $charge->end_battery_level);
        $this->assertEqualsWithDelta(1.3, $charge->energy_added_kwh, 0.001);
    }

    public function test_gap_just_over_two_minutes_is_not_bridged(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Earlier charging state at 10:00. Session "ends" here from the
        // perspective of contiguous charging timestamps.
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => '2026-04-15 10:00:00',
            'state' => 'charging',
            'battery_level' => 55.0,
            'energy_remaining' => 39.0,
            'charger_power' => 11.0,
            'rated_range' => 140.0,
            'latitude' => 39.0,
            'longitude' => -108.5,
        ]);
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => '2026-04-15 10:00:30',
            'state' => 'idle',
            'battery_level' => 55.0,
            'energy_remaining' => 39.0,
            'charger_power' => 0,
        ]);

        // New charging block starts 3 minutes later — gap between
        // consecutive charging timestamps is 3 min, which is over the
        // 2-min bridge threshold and must NOT be bridged.
        $newSessionStart = Carbon::parse('2026-04-15 10:03:00');
        for ($i = 0; $i < 12; $i++) {
            VehicleState::factory()->create([
                'vehicle_id' => $vehicle->id,
                'timestamp' => $newSessionStart->copy()->addSeconds($i * 30),
                'state' => 'charging',
                'battery_level' => 55.0 + $i * 0.5,
                'energy_remaining' => 39.0 + $i * 0.3,
                'charger_power' => 11.0,
                'rated_range' => 140.0,
                'latitude' => 39.0,
                'longitude' => -108.5,
            ]);
        }

        $endedAt = $newSessionStart->copy()->addMinutes(7);
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => $endedAt,
            'state' => 'idle',
            'battery_level' => 61.0,
            'energy_remaining' => 42.6,
            'charger_power' => 0,
        ]);

        $this->runJob($vehicle->id, $endedAt);

        $this->assertDatabaseCount('charges', 1);
        $charge = Charge::first();
        // Must capture only the new session, not bridge across the 3-minute gap.
        $this->assertEquals(
            $newSessionStart->toDateTimeString(),
            $charge->started_at->toDateTimeString(),
        );
        $this->assertEquals(55.0, $charge->start_battery_level);
    }
}
