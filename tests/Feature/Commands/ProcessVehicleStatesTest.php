<?php

namespace Tests\Feature\Commands;

use App\Models\Charge;
use App\Models\ChargePoint;
use App\Models\Drive;
use App\Models\DrivePoint;
use App\Models\Vehicle;
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
