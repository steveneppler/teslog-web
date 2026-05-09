<?php

namespace Tests\Feature\Commands;

use App\Models\Charge;
use App\Models\ChargePoint;
use App\Models\Drive;
use App\Models\DrivePoint;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessVehicleStatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_with_after_before_only_deletes_within_window(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Inside window
        $insideCharge = Charge::factory()->create([
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-04-09 10:00:00',
            'ended_at' => '2026-04-09 11:00:00',
        ]);
        $insideChargePoint = ChargePoint::create([
            'charge_id' => $insideCharge->id,
            'timestamp' => '2026-04-09 10:30:00',
            'battery_level' => 50,
            'charger_power_kw' => 120,
        ]);
        $insideDrive = Drive::factory()->create([
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-04-09 12:00:00',
            'ended_at' => '2026-04-09 12:30:00',
        ]);
        $insideDrivePoint = DrivePoint::create([
            'drive_id' => $insideDrive->id,
            'timestamp' => '2026-04-09 12:15:00',
            'latitude' => 38.5,
            'longitude' => -106.0,
        ]);

        // Outside window (before)
        $beforeCharge = Charge::factory()->create([
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-04-08 08:00:00',
            'ended_at' => '2026-04-08 09:00:00',
        ]);
        $beforeChargePoint = ChargePoint::create([
            'charge_id' => $beforeCharge->id,
            'timestamp' => '2026-04-08 08:30:00',
            'battery_level' => 40,
            'charger_power_kw' => 11,
        ]);
        $beforeDrive = Drive::factory()->create([
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-04-08 10:00:00',
            'ended_at' => '2026-04-08 10:30:00',
        ]);
        $beforeDrivePoint = DrivePoint::create([
            'drive_id' => $beforeDrive->id,
            'timestamp' => '2026-04-08 10:15:00',
            'latitude' => 38.4,
            'longitude' => -106.1,
        ]);

        // Outside window (after)
        $afterCharge = Charge::factory()->create([
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-04-10 08:00:00',
            'ended_at' => '2026-04-10 09:00:00',
        ]);
        $afterChargePoint = ChargePoint::create([
            'charge_id' => $afterCharge->id,
            'timestamp' => '2026-04-10 08:30:00',
            'battery_level' => 60,
            'charger_power_kw' => 48,
        ]);
        $afterDrive = Drive::factory()->create([
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-04-10 10:00:00',
            'ended_at' => '2026-04-10 10:30:00',
        ]);
        $afterDrivePoint = DrivePoint::create([
            'drive_id' => $afterDrive->id,
            'timestamp' => '2026-04-10 10:15:00',
            'latitude' => 38.6,
            'longitude' => -105.9,
        ]);

        $this->artisan('teslog:process-states', [
            '--vehicle' => $vehicle->id,
            '--force' => true,
            '--after' => '2026-04-09 00:00:00',
            '--before' => '2026-04-10 00:00:00',
        ])->assertSuccessful();

        // Inside-window rows were deleted by --force (0 vehicle_states means no
        // new sessions get created, so they just stay gone). Their point rows
        // go with them via the scoped subquery delete.
        $this->assertDatabaseMissing('charges', ['id' => $insideCharge->id]);
        $this->assertDatabaseMissing('charge_points', ['id' => $insideChargePoint->id]);
        $this->assertDatabaseMissing('drives', ['id' => $insideDrive->id]);
        $this->assertDatabaseMissing('drive_points', ['id' => $insideDrivePoint->id]);

        // Outside-window rows — and their point children — are untouched.
        // This is the assertion that actually exercises the window clause in
        // the ChargePoint/DrivePoint subquery deletes; without it a broken
        // window would silently nuke all points on the vehicle.
        $this->assertDatabaseHas('charges', ['id' => $beforeCharge->id]);
        $this->assertDatabaseHas('charge_points', ['id' => $beforeChargePoint->id]);
        $this->assertDatabaseHas('drives', ['id' => $beforeDrive->id]);
        $this->assertDatabaseHas('drive_points', ['id' => $beforeDrivePoint->id]);
        $this->assertDatabaseHas('charges', ['id' => $afterCharge->id]);
        $this->assertDatabaseHas('charge_points', ['id' => $afterChargePoint->id]);
        $this->assertDatabaseHas('drives', ['id' => $afterDrive->id]);
        $this->assertDatabaseHas('drive_points', ['id' => $afterDrivePoint->id]);
    }

    public function test_force_without_window_still_deletes_everything(): void
    {
        $vehicle = Vehicle::factory()->create();
        $charge = Charge::factory()->create(['vehicle_id' => $vehicle->id]);
        $drive = Drive::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->artisan('teslog:process-states', [
            '--vehicle' => $vehicle->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('charges', ['id' => $charge->id]);
        $this->assertDatabaseMissing('drives', ['id' => $drive->id]);
    }

    public function test_phantom_charge_session_with_no_energy_transfer_is_discarded(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Reproduces the pattern from the debug exports: car finishes a drive,
        // parks, then briefly enters charge_state='Enable' for 2-3 minutes
        // with low power but no actual energy transfer (battery and
        // energy_remaining stay flat).
        $base = '2026-04-06 22:50:57';
        $samples = [
            [0, 0.3, 1.0, 227.6],
            [30, 0.7, 3.0, 238.4],
            [60, 0.5, 2.0, 238.4],
            [90, 0.6, 3.0, 238.6],
            [120, 0.6, 3.0, 237.4],
        ];

        foreach ($samples as [$offsetSec, $power, $current, $voltage]) {
            VehicleState::factory()->create([
                'vehicle_id' => $vehicle->id,
                'timestamp' => \Carbon\Carbon::parse($base)->addSeconds($offsetSec),
                'state' => 'charging',
                'speed' => 0,
                'gear' => 'P',
                'climate_on' => false,
                'battery_level' => 51.95,
                'energy_remaining' => 39.06,
                'rated_range' => 146.9,
                'charger_power' => $power,
                'charger_current' => $current,
                'charger_voltage' => $voltage,
                'charge_state' => 'Enable',
            ]);
        }

        $this->artisan('teslog:process-states', [
            '--vehicle' => $vehicle->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('charges', 0);
    }

    public function test_session_with_missing_telemetry_is_kept_not_discarded_as_phantom(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Charging session where battery_level and energy_remaining are
        // unset on the boundary states. We can't prove energy was or wasn't
        // transferred, so the phantom filter must NOT discard it — the
        // duration/preconditioning filters remain the safety net.
        $base = '2026-04-08 12:00:00';
        $samples = [
            [0,   null, null],
            [60,  null, null],
            [120, null, null],
            [180, null, null],
            [240, null, null],
            [300, null, null],
        ];

        foreach ($samples as [$offsetSec, $batt, $energy]) {
            VehicleState::factory()->create([
                'vehicle_id' => $vehicle->id,
                'timestamp' => \Carbon\Carbon::parse($base)->addSeconds($offsetSec),
                'state' => 'charging',
                'speed' => 0,
                'gear' => 'P',
                'climate_on' => false,
                'battery_level' => $batt,
                'energy_remaining' => $energy,
                'rated_range' => 150,
                'charger_power' => 7.0,
                'charger_current' => 30,
                'charger_voltage' => 240,
                'charge_state' => 'Charging',
            ]);
        }

        $this->artisan('teslog:process-states', [
            '--vehicle' => $vehicle->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('charges', 1);
    }

    public function test_real_low_power_ac_charge_is_kept(): void
    {
        $vehicle = Vehicle::factory()->create();

        // Slow AC charge: 5 minutes, battery rises from 50 -> 51.5%, energy
        // rises from 35 -> 36 kWh. Same low max power as a phantom (~1 kW) but
        // with actual energy transferred — must be kept.
        $base = '2026-04-06 22:00:00';
        $samples = [
            [0,   50.00, 35.00],
            [60,  50.30, 35.20],
            [120, 50.60, 35.40],
            [180, 50.90, 35.60],
            [240, 51.20, 35.80],
            [300, 51.50, 36.00],
        ];

        foreach ($samples as [$offsetSec, $batt, $energy]) {
            VehicleState::factory()->create([
                'vehicle_id' => $vehicle->id,
                'timestamp' => \Carbon\Carbon::parse($base)->addSeconds($offsetSec),
                'state' => 'charging',
                'speed' => 0,
                'gear' => 'P',
                'climate_on' => false,
                'battery_level' => $batt,
                'energy_remaining' => $energy,
                'rated_range' => 150,
                'charger_power' => 1.2,
                'charger_current' => 5,
                'charger_voltage' => 240,
                'charge_state' => 'Charging',
            ]);
        }

        $this->artisan('teslog:process-states', [
            '--vehicle' => $vehicle->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('charges', 1);
    }

    public function test_force_does_not_touch_other_vehicles(): void
    {
        $target = Vehicle::factory()->create();
        $other = Vehicle::factory()->create();

        // Also create a target-vehicle charge + drive inside the window so the
        // force-delete path actually runs on something, giving a regression in
        // either delete query a chance to misfire.
        Charge::factory()->create([
            'vehicle_id' => $target->id,
            'started_at' => '2026-04-09 10:00:00',
            'ended_at' => '2026-04-09 11:00:00',
        ]);
        Drive::factory()->create([
            'vehicle_id' => $target->id,
            'started_at' => '2026-04-09 12:00:00',
            'ended_at' => '2026-04-09 12:30:00',
        ]);

        $otherCharge = Charge::factory()->create([
            'vehicle_id' => $other->id,
            'started_at' => '2026-04-09 10:00:00',
            'ended_at' => '2026-04-09 11:00:00',
        ]);
        $otherChargePoint = ChargePoint::create([
            'charge_id' => $otherCharge->id,
            'timestamp' => '2026-04-09 10:15:00',
            'battery_level' => 50,
            'charger_power_kw' => 11,
        ]);

        $otherDrive = Drive::factory()->create([
            'vehicle_id' => $other->id,
            'started_at' => '2026-04-09 12:00:00',
            'ended_at' => '2026-04-09 12:30:00',
        ]);
        $otherDrivePoint = DrivePoint::create([
            'drive_id' => $otherDrive->id,
            'timestamp' => '2026-04-09 12:15:00',
            'latitude' => 38.5,
            'longitude' => -106.0,
        ]);

        $this->artisan('teslog:process-states', [
            '--vehicle' => $target->id,
            '--force' => true,
            '--after' => '2026-04-09 00:00:00',
            '--before' => '2026-04-10 00:00:00',
        ])->assertSuccessful();

        // Other vehicle's rows all survive — both delete queries correctly
        // scoped to vehicle_id.
        $this->assertDatabaseHas('charges', ['id' => $otherCharge->id]);
        $this->assertDatabaseHas('charge_points', ['id' => $otherChargePoint->id]);
        $this->assertDatabaseHas('drives', ['id' => $otherDrive->id]);
        $this->assertDatabaseHas('drive_points', ['id' => $otherDrivePoint->id]);
    }
}
