<?php

namespace App\Console\Commands;

use App\Enums\ChargeType;
use App\Models\Charge;
use App\Models\ChargePoint;
use App\Models\Drive;
use App\Models\DrivePoint;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessVehicleStates extends Command
{
    protected $signature = 'teslog:process-states
        {--vehicle= : Vehicle ID (processes all if omitted)}
        {--force : Reprocess even if drives/charges already exist. When combined with --after/--before, only deletes drives/charges whose started_at falls inside the window}
        {--after= : Only process vehicle_states at or after this timestamp. With --force, also scopes the delete to drives/charges with started_at >= this value. Pick a window wider than any expected session — a drive/charge that begins before --after will not be re-detected correctly, since the state scan starts at --after}
        {--before= : Only process vehicle_states at or before this timestamp. With --force, also scopes the delete to drives/charges with started_at <= this value. Same boundary caveat as --after}';

    protected $description = 'Process vehicle states into drives and charges';

    public function handle(): int
    {
        $vehicleId = $this->option('vehicle');
        $force = $this->option('force');
        $after = $this->option('after');
        $before = $this->option('before');

        $vehicles = $vehicleId
            ? Vehicle::where('id', $vehicleId)->get()
            : Vehicle::all();

        if ($vehicles->isEmpty()) {
            $this->error('No vehicles found.');
            return self::FAILURE;
        }

        foreach ($vehicles as $vehicle) {
            $this->info("Processing vehicle: {$vehicle->name} ({$vehicle->vin})");

            if ($force) {
                // Delete existing processed data for this vehicle. When --after
                // and/or --before are provided, scope the delete to drives/charges
                // whose started_at falls within the window, so targeted backfills
                // don't nuke history outside the requested range.
                //
                // DrivePoint/ChargePoint deletes use subqueries (not plucked ID
                // lists) so we don't materialize large ID arrays in PHP or hit
                // DB placeholder limits. The whole block runs in a transaction
                // so the parent/child deletes stay consistent if one fails.
                $buildDriveQuery = function () use ($vehicle, $after, $before) {
                    $q = Drive::where('vehicle_id', $vehicle->id);
                    if ($after) {
                        $q->where('started_at', '>=', $after);
                    }
                    if ($before) {
                        $q->where('started_at', '<=', $before);
                    }
                    return $q;
                };
                $buildChargeQuery = function () use ($vehicle, $after, $before) {
                    $q = Charge::where('vehicle_id', $vehicle->id);
                    if ($after) {
                        $q->where('started_at', '>=', $after);
                    }
                    if ($before) {
                        $q->where('started_at', '<=', $before);
                    }
                    return $q;
                };

                DB::transaction(function () use ($buildDriveQuery, $buildChargeQuery) {
                    DrivePoint::whereIn('drive_id', $buildDriveQuery()->select('id'))->delete();
                    $buildDriveQuery()->delete();

                    ChargePoint::whereIn('charge_id', $buildChargeQuery()->select('id'))->delete();
                    $buildChargeQuery()->delete();
                });

                $scope = ($after || $before)
                    ? sprintf(' in window [%s .. %s]', $after ?: '-', $before ?: '-')
                    : '';
                $this->info("  Cleared existing drives and charges{$scope}.");
            } elseif (! $after) {
                // Without --force or --after, check if we should auto-detect the incremental start point
                $lastDrive = Drive::where('vehicle_id', $vehicle->id)->orderByDesc('ended_at')->first();
                $lastCharge = Charge::where('vehicle_id', $vehicle->id)->orderByDesc('ended_at')->first();

                $lastProcessed = collect([$lastDrive?->ended_at, $lastCharge?->ended_at])
                    ->filter()
                    ->max();

                if ($lastProcessed) {
                    // Back up a bit to catch any session that might span the boundary
                    $after = $lastProcessed->copy()->subMinutes(30)->toDateTimeString();
                    $this->info("  Incremental mode: processing states after {$after}");
                }
            }

            $this->processVehicle($vehicle, $after, $before);
        }

        return self::SUCCESS;
    }

    private function processVehicle(Vehicle $vehicle, ?string $after = null, ?string $before = null): void
    {
        $query = VehicleState::where('vehicle_id', $vehicle->id);
        if ($after) {
            $query->where('timestamp', '>=', $after);
        }
        if ($before) {
            $query->where('timestamp', '<=', $before);
        }

        $totalStates = $query->count();

        if ($totalStates === 0) {
            $this->warn('  No vehicle states found.');
            return;
        }

        $this->info("  Found {$totalStates} vehicle states. Processing in chunks...");

        // First pass: detect session boundaries by storing only IDs and timestamps
        $driveSessions = [];
        $chargeSessions = [];
        $currentDriveIds = [];
        $currentChargeIds = [];
        $driveGapIds = [];
        $chargeGapIds = [];
        $driveGapGears = [];
        $chargeGapGears = [];
        $lastDriveTimestamp = null;
        $lastChargeTimestamp = null;

        $bar = $this->output->createProgressBar($totalStates);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Detecting sessions...');
        $bar->start();

        $driveParkedCoord = null;
        $driveParkedSince = null;
        $driveGearStuck = false;

        $query->orderBy('timestamp')
            ->select(['id', 'state', 'timestamp', 'charger_power', 'gear', 'latitude', 'longitude'])
            ->chunk(5000, function ($states) use (&$driveSessions, &$chargeSessions, &$currentDriveIds, &$currentChargeIds, &$driveGapIds, &$chargeGapIds, &$driveGapGears, &$chargeGapGears, &$lastDriveTimestamp, &$lastChargeTimestamp, &$driveParkedCoord, &$driveParkedSince, &$driveGearStuck, $bar) {
                foreach ($states as $state) {
                    $this->accumulateSessionIds($state, 'driving', 5, $driveSessions, $currentDriveIds, $driveGapIds, $driveGapGears, $lastDriveTimestamp, $driveParkedCoord, $driveParkedSince, $driveGearStuck);
                    $this->accumulateSessionIds($state, 'charging', 2, $chargeSessions, $currentChargeIds, $chargeGapIds, $chargeGapGears, $lastChargeTimestamp);
                    $bar->advance();
                }
            });

        // Finalize remaining sessions
        if (count($currentDriveIds) >= 2) {
            $driveSessions[] = $currentDriveIds;
        }
        if (count($currentChargeIds) >= 2) {
            $chargeSessions[] = $currentChargeIds;
        }

        $bar->setMessage('Done');
        $bar->finish();
        $this->newLine();

        $this->info("  Detected " . count($driveSessions) . " drives and " . count($chargeSessions) . " charges.");

        // Second pass: load full models per session and create drives/charges
        $bar = $this->output->createProgressBar(count($driveSessions) + count($chargeSessions));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Creating drives...');
        $bar->start();

        $driveCount = 0;
        foreach ($driveSessions as $ids) {
            $session = VehicleState::whereIn('id', $ids)->orderBy('timestamp')->get();
            if ($session->count() >= 2) {
                $this->createDrive($vehicle, $session);
                $driveCount++;
            }
            $bar->advance();
        }

        $bar->setMessage('Creating charges...');
        $chargeCount = 0;
        foreach ($chargeSessions as $ids) {
            $session = VehicleState::whereIn('id', $ids)->orderBy('timestamp')->get();
            if ($session->count() >= 2) {
                $this->createCharge($vehicle, $session);
                $chargeCount++;
            }
            $bar->advance();
        }

        $bar->setMessage('Done');
        $bar->finish();
        $this->newLine();

        $this->info("  Created {$driveCount} drives and {$chargeCount} charges.");
    }

    /**
     * Lightweight session detection that only stores IDs (not full models).
     * Keeps memory usage constant regardless of total state count.
     *
     * For driving sessions, gear=P immediately ends the current session.
     * Tesla sometimes reports state=driving while parked, so gear is the
     * reliable indicator of actual movement.
     */
    private function accumulateSessionIds(
        $state,
        string $targetState,
        int $maxGapMinutes,
        array &$sessions,
        array &$currentIds,
        array &$gapIds,
        array &$gapGears,
        &$lastTimestamp,
        ?array &$parkedCoord = null,
        &$parkedSince = null,
        bool &$gearStuck = false,
    ): void {
        // For charging detection, also match states where charger_power > 1 kW
        // Tesla API sometimes reports state=idle with charge_state values like
        // QualifyLineConfig, Enable, Startup while actively charging
        $isMatch = $state->state === $targetState
            || ($targetState === 'charging' && $state->charger_power && $state->charger_power > 1);

        if ($isMatch) {
            if (! empty($gapIds) && ! empty($currentIds)) {
                $gapMinutes = $lastTimestamp->diffInMinutes($state->timestamp);

                // For driving sessions, never bridge a gap where gear=P (car was parked).
                // But if gear is known to be stuck, ignore P in the gap.
                $parkedInGap = $targetState === 'driving' && ! $gearStuck && in_array('P', $gapGears);

                if ($gapMinutes <= $maxGapMinutes && ! $parkedInGap) {
                    $currentIds = array_merge($currentIds, $gapIds);
                } else {
                    if (count($currentIds) >= 2) {
                        $sessions[] = $currentIds;
                    }
                    $currentIds = [];
                }
                $gapIds = [];
                $gapGears = [];
            }

            // For driving: detect stops where gear=P within 50m of the same spot.
            // Tesla sometimes reports gear=P while actually driving (gear stuck), so
            // we only split when consecutive gear=P states stay within 50m of the
            // initial parked position for 90+ seconds. After a split, keep discarding
            // gear=P states at the same location to avoid micro-drive fragments.
            if ($targetState === 'driving' && $state->gear === 'P') {
                $hasCoord = $state->latitude && $state->longitude;

                if ($hasCoord && $parkedCoord) {
                    $distance = $this->haversineDistance(
                        $state->latitude, $state->longitude,
                        $parkedCoord[0], $parkedCoord[1]
                    );

                    if ($distance <= 50) {
                        // Still within 50m of parked position
                        if (! empty($currentIds) && $parkedSince
                            && abs($state->timestamp->diffInSeconds($parkedSince)) >= 90) {
                            // Split: save the current session
                            if (count($currentIds) >= 2) {
                                $sessions[] = $currentIds;
                            }
                            $currentIds = [];
                        }
                        // Keep parkedCoord set so we continue discarding states here
                        return;
                    }

                    // Moved >50m from parked coord — gear is stuck, not a real stop.
                    // Mark gear as stuck so gap detection ignores P in gaps.
                    $parkedCoord = null;
                    $parkedSince = null;
                    $gearStuck = true;
                } elseif ($hasCoord) {
                    // First gear=P state — start tracking, but still add to session
                    $parkedCoord = [$state->latitude, $state->longitude];
                    $parkedSince = $state->timestamp;
                }
            } else {
                // Normal driving state (not gear=P) — reset parked tracking and gear stuck flag
                $parkedCoord = null;
                $parkedSince = null;
                $gearStuck = false;
            }

            $currentIds[] = $state->id;
            $lastTimestamp = $state->timestamp;
        } else {
            if (! empty($currentIds)) {
                $gapIds[] = $state->id;
                if ($state->gear) {
                    $gapGears[] = $state->gear;
                }
            }
        }
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function createDrive(Vehicle $vehicle, $states): void
    {
        $first = $states->first();
        $last = $states->last();

        // Look up the nearest parked/idle state before the drive for true start location.
        // Include driving+gear=P states since Tesla reports state=driving while parked.
        $preDriveState = VehicleState::where('vehicle_id', $vehicle->id)
            ->where('timestamp', '<', $first->timestamp)
            ->where(function ($q) {
                $q->where('state', '!=', 'driving')
                    ->orWhere('gear', 'P');
            })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('timestamp')
            ->first();

        // Look up the settled end location: use the LAST idle state within a window
        // after the drive ends, so GPS has time to settle after parking
        $windowEnd = $last->timestamp->copy()->addMinutes(5);
        $nextDrivingStart = VehicleState::where('vehicle_id', $vehicle->id)
            ->where('timestamp', '>', $last->timestamp)
            ->where('state', 'driving')
            ->where(function ($q) {
                $q->where('gear', '!=', 'P')->orWhereNull('gear');
            })
            ->orderBy('timestamp')
            ->value('timestamp');
        if ($nextDrivingStart && $nextDrivingStart->lt($windowEnd)) {
            $windowEnd = $nextDrivingStart;
        }

        $postDriveState = VehicleState::where('vehicle_id', $vehicle->id)
            ->where('timestamp', '>', $last->timestamp)
            ->where('timestamp', '<', $windowEnd)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($q) {
                // Include non-driving states and driving states where gear=P (parked but
                // Tesla still reports driving) — these have settled GPS coordinates
                $q->where('state', '!=', 'driving')
                    ->orWhere('gear', 'P');
            })
            ->orderByDesc('timestamp')
            ->first();

        // Use pre/post states for location if available and within 10 minutes
        $startState = $preDriveState && $first->timestamp->diffInMinutes($preDriveState->timestamp) <= 10
            ? $preDriveState : $first;
        $endState = $postDriveState && $last->timestamp->diffInMinutes($postDriveState->timestamp) <= 10
            ? $postDriveState : $last;

        // Calculate distance from odometer
        $distance = null;
        if ($first->odometer && $last->odometer) {
            $distance = $last->odometer - $first->odometer;
        }

        // Calculate energy used
        $energyUsed = null;
        if ($first->energy_remaining !== null && $last->energy_remaining !== null) {
            $energyUsed = $first->energy_remaining - $last->energy_remaining;
        }

        // Fallback: estimate energy from battery percentage and vehicle capacity
        if ($energyUsed === null && $vehicle->battery_capacity_kwh
            && $first->battery_level !== null && $last->battery_level !== null) {
            $batteryDiff = $first->battery_level - $last->battery_level;
            $energyUsed = ($batteryDiff / 100) * $vehicle->battery_capacity_kwh;
        }

        // Calculate efficiency (Wh/mi)
        $efficiency = null;
        if ($energyUsed && $distance && $distance > 0) {
            $efficiency = ($energyUsed * 1000) / $distance;
        }

        // Check for duplicate
        $existing = Drive::where('vehicle_id', $vehicle->id)
            ->where('started_at', $first->timestamp)
            ->first();
        if ($existing) {
            return;
        }

        $drive = Drive::create([
            'vehicle_id' => $vehicle->id,
            'started_at' => $first->timestamp,
            'ended_at' => $last->timestamp,
            'distance' => $distance,
            'energy_used_kwh' => $energyUsed,
            'efficiency' => $efficiency,
            'start_latitude' => $startState->latitude,
            'start_longitude' => $startState->longitude,
            'end_latitude' => $endState->latitude,
            'end_longitude' => $endState->longitude,
            'start_battery_level' => $first->battery_level,
            'end_battery_level' => $last->battery_level,
            'start_rated_range' => $first->rated_range,
            'end_rated_range' => $last->rated_range,
            'start_odometer' => $first->odometer,
            'end_odometer' => $last->odometer,
            'max_speed' => $states->max('speed'),
            'avg_speed' => $states->avg('speed'),
            'outside_temp_avg' => $states->avg('outside_temp'),
        ]);

        // Create GPS breadcrumbs — include pre/post drive states for complete route
        $allPoints = collect();

        if ($startState->id !== $first->id) {
            $allPoints->push($startState);
        }
        $allPoints = $allPoints->concat($states);
        if ($endState->id !== $last->id) {
            $allPoints->push($endState);
        }

        $points = $allPoints
            ->filter(fn ($s) => $s->latitude && $s->longitude)
            ->map(fn ($s) => [
                'drive_id' => $drive->id,
                'timestamp' => $s->timestamp,
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
                'altitude' => $s->elevation,
                'speed' => $s->speed,
                'power' => $s->power,
                'battery_level' => $s->battery_level,
                'heading' => $s->heading,
            ])->values()->all();

        foreach (array_chunk($points, 500) as $chunk) {
            DrivePoint::insert($chunk);
        }
    }

    private function createCharge(Vehicle $vehicle, $states): void
    {
        $first = $states->first();
        $last = $states->last();

        // Discard trivial charges (e.g. brief charger handshake when plugging in)
        if ($first->timestamp->diffInMinutes($last->timestamp) < 2) {
            return;
        }

        // Discard preconditioning sessions: climate draws wall power but battery
        // doesn't actually charge
        $batteryIncreased = $first->battery_level !== null
            && $last->battery_level !== null
            && $last->battery_level > $first->battery_level + 0.5;
        $climateOnThroughout = $states->every(fn ($s) => $s->climate_on);
        $maxPower = $states->max('charger_power');

        if (! $batteryIncreased && $climateOnThroughout && $maxPower < 5) {
            return;
        }

        // Determine charge type
        $chargeType = ChargeType::Ac;
        if ($maxPower && $maxPower > 50) {
            $chargeType = ChargeType::Supercharger;
        } elseif ($maxPower && $maxPower > 20) {
            $chargeType = ChargeType::Dc;
        }

        // Energy added
        $energyAdded = null;
        if ($first->energy_remaining !== null && $last->energy_remaining !== null) {
            $energyAdded = $last->energy_remaining - $first->energy_remaining;
        }

        // Fallback: estimate from battery percentage and vehicle capacity
        if ((! $energyAdded || $energyAdded == 0) && $vehicle->battery_capacity_kwh
            && $first->battery_level !== null && $last->battery_level !== null
            && $last->battery_level > $first->battery_level) {
            $batteryDiff = $last->battery_level - $first->battery_level;
            $energyAdded = round(($batteryDiff / 100) * $vehicle->battery_capacity_kwh, 2);
        }

        // Check for duplicate
        $existing = Charge::where('vehicle_id', $vehicle->id)
            ->where('started_at', $first->timestamp)
            ->first();
        if ($existing) {
            return;
        }

        // Find location from nearby states (charging states often lack GPS)
        $latitude = $first->latitude;
        $longitude = $first->longitude;
        if (! $latitude || ! $longitude) {
            $nearbyState = VehicleState::where('vehicle_id', $vehicle->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where(function ($q) use ($first, $last) {
                    $q->whereBetween('timestamp', [
                        $first->timestamp->copy()->subMinutes(30),
                        $last->timestamp->copy()->addMinutes(30),
                    ]);
                })
                ->orderByRaw('ABS(JULIANDAY(timestamp) - JULIANDAY(?))', [$first->timestamp])
                ->first();
            if ($nearbyState) {
                $latitude = $nearbyState->latitude;
                $longitude = $nearbyState->longitude;
            }
        }

        $charge = Charge::create([
            'vehicle_id' => $vehicle->id,
            'started_at' => $first->timestamp,
            'ended_at' => $last->timestamp,
            'charge_type' => $chargeType,
            'energy_added_kwh' => $energyAdded,
            'start_battery_level' => $first->battery_level,
            'end_battery_level' => $last->battery_level,
            'start_rated_range' => $first->rated_range,
            'end_rated_range' => $last->rated_range,
            'max_charger_power' => $maxPower,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        // Create charge curve points
        $points = $states->map(function ($s) use ($charge) {
            $power = $s->charger_power;
            if ((! $power || $power == 0) && $s->charger_voltage && $s->charger_current) {
                $power = round(($s->charger_voltage * $s->charger_current) / 1000, 2);
            }

            return [
                'charge_id' => $charge->id,
                'timestamp' => $s->timestamp,
                'battery_level' => $s->battery_level,
                'charger_power_kw' => $power,
                'voltage' => $s->charger_voltage,
                'current' => $s->charger_current,
                'rated_range' => $s->rated_range,
            ];
        })->all();

        foreach (array_chunk($points, 500) as $chunk) {
            ChargePoint::insert($chunk);
        }
    }
}
