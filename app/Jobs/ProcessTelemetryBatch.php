<?php

namespace App\Jobs;

use App\Events\VehicleStateChanged;
use App\Models\TelemetryRaw;
use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Services\StateDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessTelemetryBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $vehicleId,
    ) {}

    public function handle(StateDetectionService $stateDetection): void
    {
        $vehicle = Vehicle::find($this->vehicleId);
        if (! $vehicle) {
            return;
        }

        // Get unprocessed telemetry grouped by timestamp
        $unprocessed = TelemetryRaw::where('vehicle_id', $this->vehicleId)
            ->where('processed', false)
            ->orderBy('timestamp')
            ->get()
            ->groupBy(fn ($row) => $row->timestamp->format('Y-m-d H:i:s'));

        $sampleInterval = config('teslog.telemetry.state_sample_interval_seconds', 30);
        $lastState = $vehicle->latestState;
        $lastSampleTime = $lastState?->timestamp;

        // Carry-forward: build from ALL recent raw telemetry (last 5 min)
        // This ensures fields that arrived in previous job runs aren't lost
        $carryForwardKeys = [
            'battery_level', 'rated_range', 'ideal_range', 'energy_remaining',
            'charge_limit_soc', 'charge_state', 'charger_power', 'charger_voltage', 'charger_current',
            'speed', 'power', 'odometer', 'gear',
            'latitude', 'longitude', 'heading', 'elevation',
            'inside_temp', 'outside_temp', 'climate_on',
            'locked', 'sentry_mode', 'software_version',
        ];

        // Start with last known state
        $carried = [];
        if ($lastState) {
            foreach ($carryForwardKeys as $key) {
                if ($lastState->$key !== null) {
                    $carried[$key] = $lastState->$key;
                }
            }
        }

        // Overlay with recent processed raw telemetry (fills gaps from fields that arrived
        // between the last state and now but weren't in the same sample window)
        $recentRaw = TelemetryRaw::where('vehicle_id', $this->vehicleId)
            ->where('processed', true)
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->orderBy('timestamp')
            ->get();
        $recentSnapshot = $this->buildSnapshot($recentRaw);
        $carried = array_merge($carried, $recentSnapshot);

        foreach ($unprocessed as $timestamp => $fields) {
            // Check if enough time has passed for a new sample
            $currentTime = $fields->first()->timestamp;

            // Build incremental snapshot and merge with carried values
            $incremental = $this->buildSnapshot($fields);
            $carried = array_merge($carried, $incremental);

            // Prevent stale carry-forward from keeping the vehicle stuck in "charging".
            // If ChargeState wasn't freshly received in this batch AND charger power is
            // absent or zero, clear the carried charge_state so the state machine
            // can transition to idle.
            if (! isset($incremental['charge_state'])) {
                $chargerPower = $carried['charger_power'] ?? 0;
                if (! $chargerPower || $chargerPower <= 0) {
                    unset($carried['charge_state']);
                }
            }

            if ($lastSampleTime && abs($currentTime->diffInSeconds($lastSampleTime)) < $sampleInterval) {
                // Mark as processed but don't create state
                TelemetryRaw::whereIn('id', $fields->pluck('id'))->update(['processed' => true]);
                continue;
            }

            $snapshot = $carried;
            $previousState = $lastState?->state ?? 'offline';
            $newState = $stateDetection->detectState($snapshot, $previousState);

            $vehicleState = VehicleState::create(array_merge($snapshot, [
                'vehicle_id' => $this->vehicleId,
                'timestamp' => $currentTime,
                'state' => $newState,
            ]));

            // Update cached latest state pointer
            $vehicle->updateQuietly(['latest_state_id' => $vehicleState->id]);

            // Broadcast every new state to the dashboard
            VehicleStateChanged::dispatch($vehicle, $vehicleState);

            // Handle state transitions (session management)
            if ($previousState !== $newState) {
                $stateDetection->handleTransition($vehicle, $previousState, $newState, $vehicleState);
            }

            // Update firmware if changed
            if (isset($snapshot['software_version']) && $snapshot['software_version'] !== $vehicle->firmware_version) {
                $oldVersion = $vehicle->firmware_version;
                $vehicle->update(['firmware_version' => $snapshot['software_version']]);
                $vehicle->firmwareHistory()->create([
                    'version' => $snapshot['software_version'],
                    'detected_at' => $currentTime,
                    'previous_version' => $oldVersion,
                ]);
            }

            TelemetryRaw::whereIn('id', $fields->pluck('id'))->update(['processed' => true]);
            $lastState = $vehicleState;
            $lastSampleTime = $currentTime;
        }
    }

    private function buildSnapshot($fields): array
    {
        $map = [
            'BatteryLevel' => 'battery_level',
            'RatedBatteryRange' => 'rated_range',
            'RatedRange' => 'rated_range',
            'IdealBatteryRange' => 'ideal_range',
            'VehicleSpeed' => 'speed',
            'Power' => 'power',
            'ACChargingPower' => 'charger_power',
            'DCChargingPower' => 'charger_power',
            'Odometer' => 'odometer',
            'Latitude' => 'latitude',
            'Longitude' => 'longitude',
            'Heading' => 'heading',
            'GpsHeading' => 'heading',
            'Elevation' => 'elevation',
            'InsideTemp' => 'inside_temp',
            'OutsideTemp' => 'outside_temp',
            'Locked' => 'locked',
            'SentryMode' => 'sentry_mode',
            'HvacACEnabled' => 'climate_on',
            'Gear' => 'gear',
            'ChargerPower' => 'charger_power',
            'ChargerVoltage' => 'charger_voltage',
            'ChargerActualCurrent' => 'charger_current',
            'ChargeAmps' => 'charger_current',
            'ChargeLimitSoc' => 'charge_limit_soc',
            'ChargeState' => 'charge_state',
            'EnergyRemaining' => 'energy_remaining',
            'SoftwareVersion' => 'software_version',
            'Version' => 'software_version',
        ];

        $snapshot = [];
        foreach ($fields as $field) {
            $key = $map[$field->field_name] ?? null;
            if ($key) {
                $value = $field->value_numeric ?? $field->value_string;
                // Fleet Telemetry sends "<invalid>" or literal "null" for unavailable fields
                if ($value === '<invalid>' || $value === 'null') {
                    continue;
                }
                // Strip stray JSON quotes from string values (MQTT payloads may be JSON-encoded)
                if (is_string($value) && strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
                    $value = substr($value, 1, -1);
                }
                $snapshot[$key] = $value;
            }
        }

        // Map fleet telemetry string enums to expected values
        if (isset($snapshot['gear']) && is_string($snapshot['gear'])) {
            $gearMap = ['ShiftStateP' => 'P', 'ShiftStateD' => 'D', 'ShiftStateR' => 'R', 'ShiftStateN' => 'N'];
            $snapshot['gear'] = $gearMap[$snapshot['gear']] ?? $snapshot['gear'];
        }

        if (isset($snapshot['sentry_mode']) && is_string($snapshot['sentry_mode'])) {
            $snapshot['sentry_mode'] = !str_contains($snapshot['sentry_mode'], 'Off');
        }

        if (isset($snapshot['charge_state']) && is_string($snapshot['charge_state'])) {
            // Fleet telemetry sends "Idle", "Charging", "Complete", etc. — keep as-is
        }

        // Convert boolean-like fields
        foreach (['locked', 'climate_on'] as $boolField) {
            if (isset($snapshot[$boolField]) && ! is_bool($snapshot[$boolField])) {
                $snapshot[$boolField] = filter_var($snapshot[$boolField], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $snapshot;
    }
}
