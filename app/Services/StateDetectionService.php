<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleState;

class StateDetectionService
{
    public function detectState(array $snapshot, string $previousState): string
    {
        // Driving: speed > 0 or gear is D/R
        $speed = $snapshot['speed'] ?? 0;
        $gear = $snapshot['gear'] ?? '';
        if ((is_numeric($speed) && $speed > 0) || in_array($gear, ['D', 'R'])) {
            return 'driving';
        }

        // Charging detection. Tesla Fleet Telemetry sometimes reports
        // charge_state='Idle' for the bulk of an active Supercharger session while
        // charger_power is >100 kW, so treat any meaningful charger_power as a
        // definitive charging signal. The >1 kW threshold matches the batch
        // reprocessor in ProcessVehicleStates and is above spurious ~0.04 kW
        // idle-bus readings.
        $chargerPower = $snapshot['charger_power'] ?? 0;
        if (is_numeric($chargerPower) && $chargerPower > 1) {
            return 'charging';
        }

        // charge_state-based detection. Allowlist the values that mean
        // "actively drawing power" or "preparing to draw power immediately":
        //   - Charging / Starting: from Tesla's official DetailedChargeStateValue
        //     proto enum (the public surface).
        //   - Startup: the deeper-CAN-bus equivalent of Starting that real
        //     telemetry actually sends at the start of every AC home charge,
        //     usually with charger_power=0 for the first 1-2 readings before
        //     power ramps up. Including it preserves the exact charge start
        //     time on real charges.
        //
        // Other values like Enable, ClearFaults, QualifyLineConfig also come
        // from the internal state machine but only mean "charging system
        // enabled / requested" — not "energy flowing." Treating those as
        // charging produces phantom sessions when the car briefly handshakes
        // with a charger after parking (see fix in this file's git history).
        // Power-based fallbacks below cover real charging that arrives before
        // charge_state catches up.
        $chargeState = $snapshot['charge_state'] ?? '';
        $activeChargingStates = ['Charging', 'Starting', 'Startup'];
        if (in_array($chargeState, $activeChargingStates, true)) {
            return 'charging';
        }

        // Fallback: detect charging from power delivery data even when
        // charge_state is absent (Tesla doesn't always send ChargeState continuously).
        // Catches low-power AC charging (0.5–1 kW) not covered by the >1 kW check above.
        if ($chargerPower && $chargerPower > 0.5) {
            return 'charging';
        }

        // If we were driving and now stopped, transition to idle
        if ($previousState === 'driving') {
            return 'idle';
        }

        // If we were charging and charger power dropped to 0
        if ($previousState === 'charging') {
            return 'idle';
        }

        // If we have no recent data, consider offline
        // (handled elsewhere by timeout)

        // Receiving telemetry means the car is awake — transition from sleeping to idle
        if ($previousState === 'sleeping') {
            return 'idle';
        }

        // Default: maintain current state, or idle
        if ($previousState === 'idle') {
            return 'idle';
        }

        return 'idle';
    }

    public function handleTransition(Vehicle $vehicle, string $from, string $to, VehicleState $state): void
    {
        // Driving ended -> create drive
        if ($from === 'driving' && $to !== 'driving') {
            \App\Jobs\ProcessDriveJob::dispatch($vehicle->id, $state->timestamp)->delay(now()->addMinute());
        }

        // Charging ended -> create charge
        if ($from === 'charging' && $to !== 'charging') {
            \App\Jobs\ProcessChargeJob::dispatch($vehicle->id, $state->timestamp);
        }

        // Idle ended -> create idle session
        if ($from === 'idle' && $to !== 'idle') {
            \App\Jobs\ProcessIdleJob::dispatch($vehicle->id, $state->timestamp);
        }
    }
}
